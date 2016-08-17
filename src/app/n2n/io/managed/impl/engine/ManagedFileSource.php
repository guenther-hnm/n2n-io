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

use n2n\io\img\impl\ImageSourceFactory;
use n2n\io\fs\FsPath;
use n2n\io\managed\FileManagingConstraintException;
use n2n\io\managed\FileSourceThumbEngine;

class ManagedFileSource extends FileSourceAdapter {
	private $fileManagerName;
	private $dirPerm;
	private $filePerm;
	private $persistent = false;
	
	public function __construct(FsPath $fileFsPath, string $fileManagerName, string $qualifiedName, 
			string $dirPerm, string $filePerm) {
		parent::__construct($qualifiedName, $fileFsPath, null);
		$this->fileManagerName = $fileManagerName;
		$this->dirPerm = $dirPerm;
		$this->filePerm = $filePerm;
	}
	
	public function getDirPerm(): string {
		return $this->dirPerm;
	}
	
	public function getFilePerm(): string {
		return $this->filePerm;
	}
	
	public function getFileManagerName(): string {
		return $this->fileManagerName;
	}
	
	public function setPersisent($persistent) {
		$this->persistent = (boolean) $persistent;
	}
	
	public function isPersistent() {
		return $this->persistent;
	}
	
	public function move(FsPath $fsPath, $filePerm, $overwrite = false) {
		$this->ensureValid();
		
		throw new FileManagingConstraintException('File is managed by ' . $this->fileManagerName 
				. ' and can not be relocated: ' . $this->fileFsPath);
	}
	
	public function delete() {
		$this->ensureValid();
		
		throw new FileManagingConstraintException('File is managed by ' . $this->fileManagerName 
				. ' and can not be deleted: ' . $this->fileFsPath);
	}
	
	public function isThumbSupportAvailable(): bool {
		return $this->isImage();
	}
	
	public function getFileSourceThumbEngine(): FileSourceThumbEngine {
		$this->ensureValid();
		
		return new ManagedFileSourceThumbEngine($this, 
				ImageSourceFactory::getMimeTypeOfFile($this->fileFsPath), 
				$this->dirPerm, $this->filePerm);		
	}
	
	/* (non-PHPdoc)
	 * @see \n2n\io\managed\FileSource::__toString()
	 */
	public function __toString(): string {
		return $this->fileFsPath . ' (managed by ' . $this->fileManagerName . ')';		
	}
}