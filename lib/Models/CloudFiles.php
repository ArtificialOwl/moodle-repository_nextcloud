<?php


namespace repository_nextcloud\Models;


class CloudFiles {


	/** @var boolean */
	private $dynLoad;

	/** @var boolean */
	private $noSearch;

	/** @var boolean */
	private $noLogin;

	/** @var string */
	private $path;

	/** @var array */
	private $paths = [];

	/** @var array */
	private $content = [];

	/** @var string */
	private $manageLink = '';

	/** @var object */
	private $output;

	function __construct($path) {

		if ($path === '') {
			$path = '/';
		}

		$this->path = $path;
		$this->setDefault();
		$this->parsePath();

		if (has_capability('moodle/site:config', \context_system::instance())) {
			$this->setManageLink(\repository_nextcloud::getLinkToToolOauth2());
		}
	}


	/**
	 * set default values
	 */
	private function setDefault() {
		global $OUTPUT;

		$this->output = $OUTPUT;

		$this->setDynLoad(true);
		$this->setNoSearch(true);
		$this->setNoLogin(false);
		$this->addPath(get_string('nextcloud', 'repository_nextcloud'), '');
	}


	/**
	 * @return string
	 */
	public function getPath() {
		return $this->path;
	}

	/**
	 * @param bool $dynLoad
	 *
	 * @return $this
	 */
	public function setDynLoad($dynLoad) {
		$this->dynLoad = $dynLoad;

		return $this;
	}

	/**
	 * @return bool
	 */
	public function getDynLoad() {
		return $this->dynLoad;
	}


	/**
	 * @param bool $noSearch
	 *
	 * @return $this
	 */
	public function setNoSearch($noSearch) {
		$this->noSearch = $noSearch;

		return $this;
	}

	/**
	 * @return bool
	 */
	public function getNoSearch() {
		return $this->noSearch;
	}


	/**
	 * @param bool $noLogin
	 *
	 * @return $this
	 */
	public function setNoLogin($noLogin) {
		$this->noLogin = $noLogin;

		return $this;
	}

	/**
	 * @return bool
	 */
	public function getNoLogin() {
		return $this->noLogin;
	}


	public function addPath($name, $path) {
		$this->paths[] = [
			'name' => $name,
			'path' => $path
		];
	}

	public function getPaths() {
		return $this->paths;
	}


	public function setFolderContent($folders, $files) {
		ksort($files);
		ksort($folders);
		$this->content = array_merge($folders, $files);
	}

	public function getFolderContent() {
		return $this->content;
	}


	/**
	 * @param string $link
	 *
	 * @return $this
	 */
	public function setManageLink($link) {
		$this->manageLink = $link;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getManageLink() {
		return $this->manageLink;
	}


	/**
	 * This calculates all the parents paths form the current path. This is shown in the
	 * navigation bar of the file picker.
	 */
	public function parsePath() {
		$chunks = preg_split('|/|', trim($this->path, '/'));

		for ($i = 0; $i < count($chunks); $i++) {
			$this->addPath(
				urldecode($chunks[$i]),
				'/' . join('/', array_slice($chunks, 0, $i + 1)) . '/'
			);
		}
	}


	/**
	 * parse all information about all child files/folders of the
	 * current directory. If no information was received, the directory must be empty.
	 *
	 * @param array|mixed $content
	 */
	public function parseFolderContent($content) {

		if (!is_array($content)) {
			return;
		}

		$files = $folders = [];
		foreach ($content as $v) {

			$file = RemoteFile::fromWebDav($v);
			$title = substr($file->getHref(), strlen($this->getPath()));

			// feed folders and files
			if ($file->getResourceType() === 'collection') {
				$this->addContentFolder($folders, $file, $title);
			} else {
				$this->addContentFile($files, $file, $title);
			}
		}

		$this->setFolderContent($folders, $files);
	}


	/**
	 * @param array $folders
	 * @param RemoteFile $file
	 * @param string $title
	 */
	private function addContentFolder(array &$folders, RemoteFile $file, $title) {
		if ($this->getPath() === $file->getHref()) {
			return;
		}

		$folders[strtoupper($title)] = [
			'title'        => rtrim($title, '/'),
			'thumbnail'    => $this->output->pix_url(file_folder_icon(90))
										   ->out(false),
			'children'     => [],
			'datemodified' => $file->getLastModified(),
			'path'         => $file->getHref()
		];
	}


	/**
	 * @param array $files
	 * @param RemoteFile $file
	 * @param string $title
	 */
	private function addContentFile(array &$files, RemoteFile $file, $title) {

		$files[strtoupper($title)] = [
			'title'        => $title,
			'thumbnail'    => $this->output->pix_url(file_extension_icon($title, 90))
										   ->out(false),
			'size'         => $file->getContentLength(),
			'datemodified' => $file->getLastModified(),
			'source'       => $file->getHref()
		];
	}


	/**
	 * data returned as an array
	 *
	 * @return array
	 */
	public function toArray() {
		return [
			'dynload'  => $this->getDynLoad(),
			'nosearch' => $this->getNoSearch(),
			'nologin'  => $this->getNoLogin(),
			'path'     => $this->getPaths(),
			'list'     => $this->getFolderContent(),
			'manage'   => $this->getManageLink()
		];
	}


}