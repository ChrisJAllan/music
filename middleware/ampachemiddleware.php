<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Morris Jobke 2013, 2014
 * @copyright Pauli Järvinen 2018, 2019
 */

namespace OCA\Music\Middleware;

use \OCP\IRequest;
use \OCP\AppFramework\Middleware;

use \OCA\Music\AppFramework\Utility\MethodAnnotationReader;
use \OCA\Music\Db\AmpacheSessionMapper;
use \OCA\Music\Http\XMLResponse;

/**
 * Used to do the authentication and checking stuff for an ampache controller method
 * It reads out the annotations of a controller method and checks which if
 * ampache authentification stuff has to be done.
 */
class AmpacheMiddleware extends Middleware {
	private $appname;
	private $request;
	private $ampacheSessionMapper;
	private $isAmpacheCall;
	private $ampacheUser;

	/**
	 * @param Request $request an instance of the request
	 */
	public function __construct($appname, IRequest $request, AmpacheSessionMapper $ampacheSessionMapper, $ampacheUser) {
		$this->appname = $appname;
		$this->request = $request;
		$this->ampacheSessionMapper = $ampacheSessionMapper;

		// used to share user info with controller
		$this->ampacheUser = $ampacheUser;
	}

	/**
	 * This runs all the security checks before a method call. The
	 * security checks are determined by inspecting the controller method
	 * annotations
	 * @param Controller $controller the controller that is being called
	 * @param string $methodName the name of the method
	 * @throws AmpacheException when a security check fails
	 */
	public function beforeController($controller, $methodName) {

		// get annotations from comments
		$annotationReader = new MethodAnnotationReader($controller, $methodName);

		$this->isAmpacheCall = $annotationReader->hasAnnotation('AmpacheAPI');

		// don't try to authenticate for the handshake request
		if ($this->isAmpacheCall && $this->request['action'] !== 'handshake') {
			$token = null;
			if (!empty($this->request['auth'])) {
				$token = $this->request['auth'];
			} elseif (!empty($this->request['ssid'])) {
				$token = $this->request['ssid'];
			}

			if ($token !== null && $token !== '') {
				$user = $this->ampacheSessionMapper->findByToken($token);
				if ($user !== false && \array_key_exists('user_id', $user)) {
					$this->ampacheUser->setUserId($user['user_id']);
					return;
				}
			} else {
				// for ping action without token the version information is provided
				if ($this->request['action'] === 'ping') {
					return;
				}
			}
			throw new AmpacheException('Invalid Login', 401);
		}
	}

	/**
	 * If an AmpacheException is being caught, the appropiate ampache
	 * exception response is rendered
	 * @param Controller $controller the controller that is being called
	 * @param string $methodName the name of the method that will be called on
	 *                           the controller
	 * @param \Exception $exception the thrown exception
	 * @throws \Exception the passed in exception if it wasn't handled
	 * @return Response a Response object if the exception was handled
	 */
	public function afterException($controller, $methodName, \Exception $exception) {
		if ($exception instanceof AmpacheException && $this->isAmpacheCall) {
			return new XMLResponse(['root' => [
				'error' => [
					'code' => $exception->getCode(),
					'value' => $exception->getMessage()
				]
			]]);
		}
		throw $exception;
	}
}
