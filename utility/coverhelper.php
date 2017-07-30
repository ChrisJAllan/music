<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2017
 */

namespace OCA\Music\Utility;

use \OCA\Music\AppFramework\Core\Logger;
use \OCA\Music\BusinessLayer\AlbumBusinessLayer;
use \OCA\Music\Db\Cache;

use \OCP\Files\Folder;

use \Doctrine\DBAL\Exception\UniqueConstraintViolationException;

/**
 * utility to get cover image for album
 */
class CoverHelper {

	private $albumBusinessLayer;
	private $extractor;
	private $cache;
	private $logger;

	public function __construct(
			AlbumBusinessLayer $albumBusinessLayer,
			Extractor $extractor,
			Cache $cache,
			Logger $logger) {
		$this->albumBusinessLayer = $albumBusinessLayer;
		$this->extractor = $extractor;
		$this->cache = $cache;
		$this->logger = $logger;
	}

	/**
	 * get cover image of the album
	 *
	 * @param int $albumId
	 * @param string $userId
	 * @param Folder $rootFolder
	 * @return array|null Image data in format accepted by \OCA\Music\Http\FileResponse
	 */
	public function getCover($albumId, $userId, $rootFolder) {
		$response = $this->getCoverFromCache($albumId, $userId);
		if ($response === null) {
			$response = $this->readCover($albumId, $userId, $rootFolder);
			if ($response !== null) {
				$this->addCoverToCache($albumId, $userId, $response);
			}
		}
		return $response;
	}

	/**
	 * get cover image of the album if it is cached
	 * 
	 * @param int $albumId
	 * @param string $userId
	 * @param bool $asBase64
	 * @return array|null Image data in format accepted by \OCA\Music\Http\FileResponse
	 */
	public function getCoverFromCache($albumId, $userId, $asBase64 = false) {
		$cached = $this->cache->get($userId, 'cover_' . $albumId);
		if ($cached !== null) {
			$delimPos = strpos($cached, '|');
			$mime = substr($cached, 0, $delimPos);
			$content = substr($cached, $delimPos + 1);
			if (!$asBase64) {
				$content = base64_decode($content);
			}
			return ['mimetype' => $mime, 'content' => $content];
		}
		return null;
	}

	/**
	 * Cache the given cover image data
	 * @param int $albumId
	 * @param string $userId
	 * @param array $coverData
	 */
	public function addCoverToCache($albumId, $userId, $coverData) {
		try {
			// support both the format generated by GetID3 and the format used in \OCA\Music\Http\FileResponse
			$mime = array_key_exists('mimetype', $coverData) ? $coverData['mimetype'] : $coverData['image_mime'];
			$content = array_key_exists('content', $coverData) ? $coverData['content'] : $coverData['data'];

			if ($mime && $content) {
				$this->cache->add($userId, 'cover_' . $albumId, $mime . '|' . base64_encode($content));
				$this->cache->remove($userId, 'collection');
			}
		}
		catch (UniqueConstraintViolationException $ex) {
			$this->logger->log("Tried to cache cover of album $albumId which is already cached. Ignoring.", 'warn');
		}
	}

	/**
	 * Remove cover image from cache if it is there. Silently do nothing if there
	 * is no cached cover.
	 * @param int $albumId
	 * @param string $userId
	 */
	public function removeCoverFromCache($albumId, $userId) {
		$this->cache->remove($userId, 'cover_' . $albumId);
	}

	/**
	 * @param integer $albumId
	 * @param string $userId
	 * @parma Folder $rootFolder
	 * @return array|null Image data in format accepted by \OCA\Music\Http\FileResponse
	 */
	private function readCover($albumId, $userId, $rootFolder) {
		$response = null;
		$album = $this->albumBusinessLayer->find($albumId, $userId);
		$coverId = $album->getCoverFileId();

		if ($coverId > 0) {
			$nodes = $rootFolder->getById($coverId);
			if (count($nodes) > 0) {
				// get the first valid node (there shouldn't be more than one node anyway)
				$node = $nodes[0];
				$mime = $node->getMimeType();

				if (0 === strpos($mime, 'audio')) { // embedded cover image
					$cover = $this->extractor->parseEmbeddedCoverArt($node);

					if ($cover !== null) {
						$response = ['mimetype' => $cover['image_mime'], 'content' => $cover['data']];
					}
				}
				else { // separate image file
					$response = ['mimetype' => $mime, 'content' => $node->getContent()];
				}
			}

			if ($response === null) {
				$this->logger->log("Requested cover not found for album $albumId, coverId=$coverId", 'error');
			}
		}

		return $response;
	}

}
