<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2013, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace lithium\storage\cache\adapter;

use Closure;
use lithium\storage\Cache;

/**
 * An Alternative PHP Cache (APC) cache adapter implementation leveraging the user-
 * space caching features (not the opcode caching features) of the cache.
 *
 * This adapter requires `pecl/apc` or `pecl/apcu` to be installed. The extension
 * and user-space caching must be enabled according to the extension documention.
 *
 * This adapter natively handles multi-key reads/writes/deletes, natively
 * provides serialization features and supports atomic increment/decrement
 * operations as well as clearing the entire user-space cache.
 *
 * Cached item persistence is not guaranteed. Infrequently used items will
 * be evicted from the cache when there is no room to store new ones.
 *
 * A simple configuration can be accomplished as follows:
 *
 * {{{
 * Cache::config(array(
 *     'default' => array('adapter' => 'Apc')
 * ));
 * }}}
 *
 * @link http://pecl.php.net/package/APCu
 * @link http://pecl.php.net/package/APC
 * @link http://php.net/manual/en/book.apc.php
 * @see lithium\storage\Cache::key()
 */
class Apc extends \lithium\storage\cache\Adapter {

	/**
	 * Class constructor.
	 *
	 * @param array $config
	 */
	public function __construct(array $config = array()) {
		$defaults = array(
			'prefix' => '',
			'expiry' => '+1 hour'
		);
		parent::__construct($config + $defaults);
	}

	/**
	 * Write values to the cache. All items to be cached will receive an
	 * expiration time of `$expiry`.
	 *
	 * @param array $keys Key/value pairs with keys to uniquely identify the to-be-cached item.
	 * @param string|integer $expiry A `strtotime()` compatible cache time or TTL in seconds.
	 *                       To persist an item use `\lithium\storage\Cache::PERSIST`.
	 * @return Closure Function returning boolean `true` on successful write, `false` otherwise.
	 */
	public function write(array $keys, $expiry = null) {
		$expiry = $expiry || $expiry === Cache::PERSIST ? $expiry : $this->_config['expiry'];

		return function($self, $params) use ($expiry) {
			if (!$expiry || $expiry === Cache::PERSIST) {
				$ttl = 0;
			} elseif (is_int($expiry)) {
				$ttl = $expiry;
			} else {
				$ttl = strtotime($expiry) - time();
			}
			return apc_store($params['keys'], null, $ttl) === array();
		};
	}

	/**
	 * Read values from the cache. Will attempt to return an array of data
	 * containing key/value pairs of the requested data.
	 *
	 * @param array $keys Keys to uniquely identify the cached items.
	 * @return Closure Function returning cached values keyed by cache keys
	 *                 on successful read, keys which could not be read will
	 *                 not be included in the results array.
	 */
	public function read(array $keys) {
		return function($self, $params) {
			return apc_fetch($params['keys']);
		};
	}

	/**
	 * Will attempt to remove specified keys from the user space cache.
	 *
	 * @param array $keys Keys to uniquely identify the cached items.
	 * @return Closure Function returning `true` on successful delete, `false` otherwise.
	 */
	public function delete(array $keys) {
		return function($self, $params) {
			return apc_delete($params['keys']) === array();
		};
	}

	/**
	 * Performs an atomic decrement operation on specified numeric cache item.
	 *
	 * Note that, as per the APC specification:
	 * If the item's value is not numeric, the decrement operation has no effect
	 * on the key - it retains it's original non-integer value.
	 *
	 * @param string $key Key of numeric cache item to decrement
	 * @param integer $offset Offset to decrement - defaults to 1.
	 * @return Closure Function returning item's new value on successful decrement, else `false`
	 */
	public function decrement($key, $offset = 1) {
		return function($self, $params) use ($offset) {
			return apc_dec($params['key'], $offset);
		};
	}

	/**
	 * Performs an atomic increment operation on specified numeric cache item.
	 *
	 * Note that, as per the APC specification:
	 * If the item's value is not numeric, the increment operation has no effect
	 * on the key - it retains it's original non-integer value.
	 *
	 * @param string $key Key of numeric cache item to increment
	 * @param integer $offset Offset to increment - defaults to 1.
	 * @return Closure Function returning item's new value on successful increment, else `false`
	 */
	public function increment($key, $offset = 1) {
		return function($self, $params) use ($offset) {
			return apc_inc($params['key'], $offset);
		};
	}

	/**
	 * Clears user-space cache
	 *
	 * @return mixed True on successful clear, false otherwise
	 */
	public function clear() {
		return apc_clear_cache('user');
	}

	/**
	 * Determines if the APC extension has been installed and
	 * if the userspace cache is available.
	 *
	 * @return boolean `true` if enabled, `false` otherwise.
	 */
	public static function enabled() {
		$loaded = extension_loaded('apc');
		$isCli = (php_sapi_name() === 'cli');
		$enabled = (!$isCli && ini_get('apc.enabled')) || ($isCli && ini_get('apc.enable_cli'));
		return ($loaded && $enabled);
	}
}

?>