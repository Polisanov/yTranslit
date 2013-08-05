<?php
/**
 * A transliteration service implementation class for MODx Revolution.
 *
 * @package modx
 * @subpackage ytranslit
 */
class modTransliterate {

	/**
	 * A reference to the modX instance communicating with this service instance.
	 * @var modX
	 */
	public $modx = null;
	/**
	 * A collection of options.
	 * @var array
	 */
	public $options = array();

	/**
	 * Constructs a new instance of the modTransliterate class.
	 *
	 * Use modX::getService() to get an instance of the translit service; do not manually construct this class.
	 *
	 * @param modX &$modx A reference to a modX instance.
	 * @param array $options An array of options for configuring the modTransliterate instance.
	 */
	public function __construct(modX &$modx, array $options = array()) {
		$this->modx = & $modx;
		$this->options = $options;
	}

	/**
	 * Translate a string using a named transliteration table.
	 *
	 * @param string $string The string to transliterate.
	 * @return string The translated string.
	 */
	public function translate($string) {
		$exclude = $this->modx->getOption('friendly_alias_ytranslit_exclude', '', '/^[_-a-zA-z\d\s\:\(\)]+$/i');
		if (preg_match($exclude, $string)) {
			return $string;
		}
		
		$service = $this->modx->getOption('friendly_alias_ytranslit_url', '', 'https://translate.yandex.net/api/v1.5/tr.json/translate?key=[[+key]]&lang=ru-en&text=', true);
		$key = trim($this->modx->getOption('friendly_alias_ytranslit_key', null, ''));
		if (empty($key)) {
			$this->modx->log(modX::LOG_LEVEL_ERROR, '[yTranslit] You must specify the API key! Please, get it from http://api.yandex.ru/key/form.xml?service=trnsl.');
			return $string;
		}
		else {
			$request = str_replace('[[+key]]', $key, $service) . urlencode($string);
			if (function_exists('curl_init')) {
				$timeout = $this->modx->getOption('friendly_alias_ytranslit_timeout', '', 1, true);
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $request);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, $timeout);
				curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
				$result = curl_exec($ch);
			}
			else {
				$result = file_get_contents($request);
			}

			$arr = $this->modx->fromJSON($result);
			if (!is_array($arr)) {
				$this->modx->log(modX::LOG_LEVEL_ERROR, "[yTranslit] Service unavailable.\nRequest: $request.\nResponse: $result");
				return $string;
			}
			else if ($arr['code'] != 200 || empty($arr['text'][0])) {
				$this->modx->log(modX::LOG_LEVEL_ERROR, '[yTranslit] Service returned an error. ' . print_r($arr,1));
				return $string;
			}
			else {
				return $arr['text'][0];
			}
		}
	}
}