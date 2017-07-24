<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

//namespace repository_nextcloud;

/**
 * ownCloud repository plugin library.
 *
 * @package    repository_nextcloud
 * @copyright  2017 Westfälische Wilhelms-Universität Münster (WWU Münster)
 * @author     Projektseminar Uni Münster
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/** @noinspection PhpUndefinedVariableInspection */
require_once($CFG->dirroot . '/repository/lib.php');

require_once('lib/Exceptions/ToolOauth2SetupIncompleteException.php');
require_once('lib/Models/RemoteFile.php');
require_once('lib/Models/CloudFiles.php');

use repository_nextcloud\Exceptions\ToolOauth2SetupIncompleteException;
use repository_nextcloud\Models\CloudFiles;

use tool_oauth2owncloud\owncloud;


class repository_nextcloud extends repository {

	/** @var owncloud */
	private $nc;

	/** @var stdClass */
	private $cfg;

	public function __construct($repoId, $context = SYSCONTEXTID, $options = array()) {
		parent::__construct($repoId, $context, $options);

		$this->setGlobals();


		try {
			$returnUrl = new moodle_url(
				'/repository/repository_callback.php', [
														 'callback' => 'yes',
														 'repo_id'  => $repoId,
														 'sesskey'  => sesskey(),
													 ]
			);
			$this->nc = new owncloud($returnUrl);
			$this->checkToolOauth2Setup();
		} catch (ToolOauth2SetupIncompleteException $e) {
			self::printWarning();
		}
	}


	public static function getLinkToToolOauth2() {
		global $CFG;

		return $CFG->wwwroot . '/' . $CFG->admin . '/settings.php?section=oauth2owncloud';
	}


	/**
	 * get globals from moodle and set as a local variable.
	 */
	private function setGlobals() {
		global $CFG;

		$this->cfg = $CFG;
	}

	/**
	 * Check if setup of the tool_oauth2owncloud is done.
	 *
	 * @throws ToolOauth2SetupIncompleteException
	 */
	private function checkToolOauth2Setup() {
		if ($this->nc->check_data() === false) {
			throw new ToolOauth2SetupIncompleteException('Oauth2 Setup is not complete');
		}
	}


	/**
	 * Output method, which prints a warning inside an activity, which uses the ownCloud repository.
	 */
	private static function printWarning() {
		global $OUTPUT;

		$str = get_string('missing_settings_admin', 'tool_oauth2owncloud');

		if (has_capability('moodle/site:config', context_system::instance())) {
			print($OUTPUT->notification(
				'<a href="' . self::getLinkToToolOauth2()
				. '" target="_blank" rel="noopener noreferrer">' . $str
				. '</a>', 'warning'
			));
		} else {
			print($OUTPUT->notification($str));
		}
	}


	/**
	 * If the plugin is set to hidden in the settings or any client settings date is missing,
	 * the plugin is set to invisible and thus, not shown in the file picker.
	 *
	 * @return bool false, if set to hidden or settings data is missing.
	 */
	public function is_visible() {
		try {
			$this->checkToolOauth2Setup();

			return (parent::is_visible());
		} catch (ToolOauth2SetupIncompleteException $e) {
			return false;
		}
	}


	/**
	 * This function does exactly the same as in the WebDAV repository. The only difference is, that
	 * the ownCloud OAuth2 client uses OAuth2 instead of Basic Authentication.
	 *
	 * @param string $url relative path to the file.
	 * @param string $title title of the file.
	 *
	 * @return array|bool returns either the moodle path to the file or false.
	 */
	public function get_file($url, $title = '') {
		$url = urldecode($url);
		$path = $this->prepare_file($title);
		if (!$this->nc->open()) {
			return false;
		}

		$this->nc->get_file($url, $path);

		return array('path' => $path);
	}


	/**
	 * This function does exactly the same as in the WebDAV repository. The only difference is, that
	 * the ownCloud OAuth2 client uses OAuth2 instead of Basic Authentication.
	 *
	 * @param string $path relative path to the directory or file.
	 * @param string $page page number (given multiple pages of elements).
	 *
	 * @return array directory properties.
	 */
	public function get_listing($path = '', $page = '') {

		$ncFiles = new CloudFiles($path);
		if (!$this->nc->open()) {
			return $ncFiles->toArray();
		}

		$ncFiles->parseFolderContent($this->nc->get_listing(urldecode($ncFiles->getPath())));

		return $ncFiles->toArray();
	}



	/**
	 * Method to generate a download link for a chosen file (in the file picker).
	 * Creates a share for the chosen file and fetches the specific file ID through
	 * the OCS Share API (ownCloud).
	 *
	 * @param string $url relative path to the chosen file
	 *
	 * @return string the generated downloadLink.
	 * @throws Exception
	 */
	public function get_link($url) {
		if ($url === '') {
			throw new Exception('get_link on empty path');
		}
		$response = $this->nc->get_link($url);

		return $response['link'];
	}


	/**
	 * This method converts the source from the file picker (chosen by the user) into
	 * information, which will be received by methods that fetch files/references from
	 * the ownCloud server.
	 *
	 * @param string $source source of the file, returned by repository as 'source' and received
	 *     back from user (not cleaned)
	 *
	 * @return string file reference, ready to be stored
	 */
	public function get_file_reference($source) {
		$useFileReference = optional_param('usefilereference', false, PARAM_BOOL);

		$reference = $source;

		// If a filereference was requested, a public link to the file has to be generated and returned.
		if ($useFileReference) {
			$reference = $this->get_link($source);
		}

		// Otherwise, the simple relative path to the file is enough.
		return $reference;
	}

	/**
	 * Method that generates a reference link to the chosen file.
	 *
	 * @param stored_file $storedFile
	 * @param int $lifetime
	 * @param int $filter
	 * @param bool $forceDownload
	 * @param array|null $options
	 */
	public function send_file(
		$storedFile, $lifetime = 86400, $filter = 0, $forceDownload = false, array $options = null
	) {
		// Delivers a download link to the concerning file.
		redirect($storedFile->get_reference());
	}


	/**
	 * Function which checks whether the user is logged in on the ownCloud instance.
	 *
	 * @return bool false, if no Access Token is set or can be requested.
	 */
	public function check_login() {
		return $this->nc->check_login();
	}


	/**
	 * Prints a simple Login Button which redirects to an authorization window from the cloud.
	 *
	 * @return mixed login window properties.
	 */
	public function print_login() {
		$url = $this->nc->get_login_url();
		if ($this->options['ajax']) {
			$ret = [];
			$btn = new \stdClass();
			$btn->type = 'popup';
			$btn->url = $url->out(false);
			$ret['login'] = array($btn);

			return $ret;
		} else {
			echo html_writer::link(
				$url, get_string('login', 'repository'),
				['target' => '_blank', 'rel' => 'noopener noreferrer']
			);
		}
	}

	/**
	 * Deletes the held Access Token and prints the Login window.
	 *
	 * @return array login window properties.
	 */
	public function logout() {
		$this->nc->log_out();
		set_user_preference('oC_token', null);

		return $this->print_login();
	}

	/**
	 * Sets up access token after the redirection from ownCloud.
	 */
	public function callback() {
		$this->nc->check_login();
	}

	/**
	 * This method adds a notification to the settings form, which redirects to the OAuth 2.0
	 * client.
	 *
	 * @codeCoverageIgnore
	 *
	 * @param moodleform $mform Moodle form (passed by reference)
	 * @param string $classname repository class name
	 */
	public static function type_config_form($mform, $classname = 'repository') {
		global $OUTPUT;

		// A notification is added to the settings page in form of a notification.
		$html = $OUTPUT->notification(
			get_string(
				'settings', 'repository_nextcloud',
				'<a href="' . self::getLinkToToolOauth2()
				. '" target="_blank" rel="noopener noreferrer">' .
				get_string('oauth2', 'repository_nextcloud') . '</a>'
			), 'warning'
		);

		$mform->addElement('html', $html);

		parent::type_config_form($mform);
	}

	/**
	 * Method to define which filetypes are supported (hardcoded can not be changed in Admin Menu)
	 *
	 * For a full list of possible types and groups, look in lib/filelib.php, function
	 * get_mimetypes_array()
	 *
	 * @return string '*' means this repository support any files
	 */
	public function supported_filetypes() {
		return '*';
	}

	/**
	 * Method to define which Files are supported (hardcoded can not be changed in Admin Menu)
	 *
	 * Can choose FILE_REFERENCE|FILE_INTERNAL|FILE_EXTERNAL
	 * FILE_INTERNAL - the file is uploaded/downloaded and stored directly within the Moodle file
	 * system FILE_EXTERNAL - the file stays in the external repository and is accessed from there
	 * directly FILE_REFERENCE - the file may be cached locally, but is automatically synchronised,
	 * as required, with any changes to the external original
	 *
	 * @return int return type bitmask supported
	 */
	public function supported_returntypes() {
		return FILE_INTERNAL | FILE_EXTERNAL | FILE_REFERENCE;
	}
}