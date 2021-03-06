<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @copyright Morris Jobke 2013, 2014
 */

namespace OCA\Music\BusinessLayer;

use \OCA\Music\AppFramework\BusinessLayer\BusinessLayer;
use \OCA\Music\AppFramework\Core\Logger;

use \OCA\Music\Db\Artist;
use \OCA\Music\Db\ArtistMapper;
use \OCA\Music\Db\SortBy;

use \OCA\Music\Utility\Util;

class ArtistBusinessLayer extends BusinessLayer {
	private $logger;

	public function __construct(ArtistMapper $artistMapper, Logger $logger) {
		parent::__construct($artistMapper);
		$this->logger = $logger;
	}

	/**
	 * Finds all artists who have at least one album
	 * @param string $userId the name of the user
	 * @param integer $sortBy sort order of the result set
	 * @return \OCA\Music\Db\Artist[] artists
	 */
	public function findAllHavingAlbums($userId, $sortBy=SortBy::None) {
		return $this->mapper->findAllHavingAlbums($userId, $sortBy);
	}

	/**
	 * Returns all artists with the given ids
	 * @param array $artistIds the ids of the artists
	 * @param string $userId the name of the user
	 * @return \OCA\Music\Db\Artist[] artists
	 */
	public function findMultipleById($artistIds, $userId) {
		return $this->mapper->findMultipleById($artistIds, $userId);
	}

	/**
	 * Adds an artist if it does not exist already or updates an existing artist
	 * @param string $name the name of the artist
	 * @param string $userId the name of the user
	 * @return \OCA\Music\Db\Artist The added/updated artist
	 */
	public function addOrUpdateArtist($name, $userId) {
		$artist = new Artist();
		$artist->setName(Util::truncate($name, 256)); // some DB setups can't truncate automatically to column max size
		$artist->setUserId($userId);
		$artist->setHash(\hash('md5', \mb_strtolower($name)));
		return $this->mapper->insertOrUpdate($artist);
	}
}
