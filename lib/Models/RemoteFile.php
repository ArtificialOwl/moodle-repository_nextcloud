<?php


namespace repository_nextcloud\Models;


class RemoteFile {

	/** @var string */
	private $href;

	/** @var int */
	private $lastModified;

	/** @var string */
	private $resourceType = '';

	/** @var string */
	private $contentLength = '';


	function __construct() {
	}

	/**
	 * parse href and retrieve file path
	 *
	 * @param $href
	 */
	public function parseHref($href) {
		$webDavPath = trim(get_config('tool_oauth2owncloud', 'path'), '/ ');
		$link = substr(urldecode(ltrim($href, '/ ')), strlen($webDavPath));
		$this->setHref($link);
	}

	/**
	 * @param $href
	 *
	 * @return $this
	 */
	public function setHref($href) {
		$this->href = $href;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getHref() {
		return $this->href;
	}


	/**
	 * @param $lastModified
	 *
	 * @return $this
	 */
	public function setLastModified($lastModified) {

		$this->lastModified = strtotime($lastModified);

		return $this;
	}

	/**
	 * @return int
	 */
	public function getLastModified() {
		return $this->lastModified;
	}


	/**
	 * @param $resourceType
	 *
	 * @return $this
	 */
	public function setResourceType($resourceType) {

		$this->resourceType = $resourceType;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getResourceType() {
		return $this->resourceType;
	}

	/**
	 * @param $contentLength
	 *
	 * @return $this
	 */
	public function setContentLength($contentLength) {
		$this->contentLength = $contentLength;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getContentLength() {
		return $this->contentLength;
	}


	/**
	 * @param $arr
	 *
	 * @return RemoteFile
	 */
	public static function fromWebDav(array $arr) {
		$file = new RemoteFile();

		$file->parseHref($arr['href']);
		if (key_exists('lastmodified', $arr)) {
			$file->setLastModified($arr['lastmodified']);
		}

		if (key_exists('resourcetype', $arr)) {
			$file->setResourceType($arr['resourcetype']);
		}

		if (key_exists('getcontentlength', $arr)) {
			$file->setContentLength($arr['getcontentlength']);
		}

		return $file;
	}

}

