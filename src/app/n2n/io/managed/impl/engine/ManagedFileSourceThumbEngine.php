<?php
/*
 * Copyright (c) 2012-2016, Hofmänner New Media.
 * DO NOT ALTER OR REMOVE COPYRIGHT NOTICES OR THIS FILE HEADER.
 *
 * This file is part of the N2N FRAMEWORK.
 *
 * The N2N FRAMEWORK is free software: you can redistribute it and/or modify it under the terms of
 * the GNU Lesser General Public License as published by the Free Software Foundation, either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * N2N is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even
 * the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details: http://www.gnu.org/licenses/
 *
 * The following people participated in this project:
 *
 * Andreas von Burg.....: Architect, Lead Developer
 * Bert Hofmänner.......: Idea, Community Leader, Marketing
 * Thomas Günther.......: Developer, Hangar
 */
namespace n2n\io\managed\impl\engine;

use n2n\io\managed\img\ImageDimension;
use n2n\io\img\ImageResource;
use n2n\io\img\impl\ImageSourceFactory;
use n2n\io\fs\FsPath;
use n2n\io\managed\FileSourceThumbEngine;
use n2n\io\managed\FileManagingException;
use n2n\io\managed\FileSource;

class ManagedFileSourceThumbEngine implements FileSourceThumbEngine {
	const THUMB_FOLDER_ATTRIBUTE_SEPARATOR = '-';

	private $fileSource;
	private $mimeType;
	private $dirPerm;
	private $filePerm;
	
	public function __construct(ManagedFileSource $fileSource, $mimeType, $dirPerm, $filePerm) {
		$this->fileSource = $fileSource;
		$this->mimeType = $mimeType;
		$this->dirPerm = $dirPerm;
		$this->filePerm = $filePerm;
	}
	
	public static function dimensionToDirName(ImageDimension $dimension) {
		try {
			return QualifiedNameBuilder::buildResFolderName((string) $dimension);
		} catch (\InvalidArgumentException $e) {
			throw new FileManagingException('Failed to create ThumbFileSource due to invalid ImageDimension idExt: ' 
				. $dimension->getIdExt(), 0, $e);
		}
		
// 		return QualifiedNameBuilder::buildResFolderName(implode(self::THUMB_FOLDER_ATTRIBUTE_SEPARATOR,
// 				array($dimension->getWidth(), $dimension->getHeight(), (boolean) $dimension->isCrop())));
	}
	
	public static function isDimensionDirName(string $dirName): bool {
		return QualifiedNameBuilder::isResDirName($dirName);
	}
	
	public static function dirNameToDimension(string $dirName): ImageDimension {
		if (null !== ($resName = QualifiedNameBuilder::parseResName($dirName))) {
			return ImageDimension::createFromString($resName);
		}
		
		return null;
	}
	
// 	private function dirNameToDimension($dirName) {
// 		$dimAttrs = explode(self::THUMB_FOLDER_ATTRIBUTE_SEPARATOR,
// 				mb_substr($dirName, mb_strlen(self::THUMB_FOLDER_PREFIX)));
	
// 		if (sizeof($dimAttrs) < 3) {
// 			throw new \InvalidArgumentException();
// 		}
	
// 		return new ImageDimension((int) $dimAttrs[0], (int) $dimAttrs[1], (boolean)$dimAttrs[2]);
// 	}
	
	private function createThumbFilePath(ImageDimension $imageDimension) {
		$fsPath = $this->fileSource->getFileFsPath();
		return $fsPath->getParent()->ext(self::dimensionToDirName($imageDimension))->ext($fsPath->getName());
	}
	
	private function createThumbFileSource(FsPath $fileFsPath, ImageDimension $imageDimension) {
		$thumbFileSource = new ManagedThumbFileSource($fileFsPath, $imageDimension, $this->mimeType, $this->fileSource);
		if ($this->fileSource->isHttpaccessible()) {
			$fileUrl = $this->fileSource->getUrl();
			$thumbUrl = $fileUrl->chPath($fileUrl->getPath()->getParent()->ext($fileFsPath->getParent()->getName(), $fileFsPath->getName()));
			$thumbFileSource->setUrl($thumbUrl);
		}
		return $thumbFileSource;
	}
	
	public function getByDimension(ImageDimension $imageDimension) {
		$fileFsPath = $this->createThumbFilePath($imageDimension);
		if ($fileFsPath->exists()) {
			return $this->createThumbFileSource($fileFsPath, $imageDimension);
		}
		return null;
	}
	
	public function create(ImageResource $imageResource, ImageDimension $imageDimension): FileSource {
		$fileFsPath = $this->createThumbFilePath($imageDimension);
		$fileFsPath->mkdirsAndCreateFile($this->dirPerm, $this->filePerm);	
		
		ImageSourceFactory::createFromFileName($fileFsPath, $this->mimeType)
				->saveImageResource($imageResource);
				
		return $this->createThumbFileSource($fileFsPath, $imageDimension);
	}
	
	/**
	 * @return \n2n\io\managed\img\ImageDimension[]
	 */
	public function getPossibleImageDimensions(): array {
		$imageDimensions = array();
		foreach ($this->fileSource->getFileFsPath()->getParent()
				->getChildren(QualifiedNameBuilder::RES_FOLDER_PREFIX . '*') as $thumbFsPath) {
			$imageDimensions[] = self::dirNameToDimension($thumbFsPath->getName());
		}
		return $imageDimensions; 
	}
	
	/**
	 * @return \n2n\io\managed\img\ImageDimension[]
	 */
	public function getUsedImageDimensions(): array {
		$imageDimensions = array();
		foreach ($this->findThumbFsPaths() as $thumbFsPath) {
			$imageDimensions[] = self::dirNameToDimension($thumbFsPath->getParent()->getName());
		}
		return $imageDimensions;
	}
	
	private function findThumbFsPaths() {
		$fsPath = $this->fileSource->getFileFsPath();
		return $fsPath->getParent()->getChildren(QualifiedNameBuilder::RES_FOLDER_PREFIX .
				'*' . DIRECTORY_SEPARATOR . $fsPath->getName());
	}
	
	public function clear() {
		$fsPath = $this->fileSource->getFileFsPath();
		
		foreach ($this->findThumbFsPaths() as $filePath) {
			$filePath->delete();
		}
	}
	
	public function getAll() {
		$fsPath = $this->fileSource->getFsPath();
		
		$thumbFileSources = array();
		foreach ($fsPath->getChildren(self::THUMB_FOLDER_PREFIX . '*' . DIRECTORY_SEPARATOR 
				. $fsPath->getName()) as $filePath) {
			$dimension = $this->dirNameToDimension($filePath->getParent()->getName());
			$thumbFileSources[$dimension->__toString()] = $this->createThumbFileSource($filePath, $dimension);
		}
		return $thumbFileSources;
	}
}