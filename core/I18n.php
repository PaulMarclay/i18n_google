<?php
    class I18n extends Ancestor {
        private $_baseLang  = '';
        private $_toLang    = '';
        private $_session;
        
        public function __construct() {
            $this->_session = Api::getSession();
        }
        
        private function getSession() {
            return $this->_session;
        }
        
        private function _getAcceptLanguages() {
            $langs      = array();
            $lang_parse = array();
            
            if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
                preg_match_all('/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i', $_SERVER['HTTP_ACCEPT_LANGUAGE'], $lang_parse);

                if (count($lang_parse[1])) {
                    $langs = array_combine($lang_parse[1], $lang_parse[4]);

                    foreach ($langs as $lang => $val) {
                        if ($val === '') $langs[$lang] = 1;
                    }

                    arsort($langs, SORT_NUMERIC);
                }
            }
            
            return $langs;
        }
        
        public function setToLang($lang) {
            $session    = $this->getSession();
            $langFile   = Api::getPath('tmp').'i18n/'.$lang.'yml';
            
            // si existe el archivo de datos, cargarlo.
            if (file_exists($langFile)) {
                $langData = Yaml_SfYaml::load($langFile);
            }
            $langCache[$lang] = $langData;
            
            // guardar a archivo el lenguaje actual.
            $this->cacheToFile($this->_getToLang());
            // eliminar de sesion el lenguaje actual.
            unset($langCache[$this->_getToLang()]);
            // actualizar cache de lenguajes.
            $session->setLangCache($langCache);
            
            $this->_toLang = $lang;
            $session->setToLang($lang);
        }

        private function _getDefaultLanguage() {
            $langs = $this->_getAcceptLanguages();
            
            if ($langs) {
                $langKeys = array_keys($langs);
                return reset($langKeys);
            }
            
            return array();
        }
        
        private function _getBaseLang() {
            $session = $this->getSession();
            if ($session->getBaseLang()) {
                return $session->getBaseLang();
            } else {
                $config = Api::getConfig();
                $this->_baseLang = $config['base_lang'];
                $session->setBaseLang($this->_baseLang);
                return $this->_baseLang;
            }
        }

        private function _getToLang() {
            $session = $this->getSession();
            if ($session->getToLang()) {
                return $session->getToLang();
            } else {
                $lang = $this->_getDefaultLanguage();
                if ($lang) {
                    return $lang;
                } else {
                    $config = Api::getConfig();
                    $this->_toLang = $config['to_lang'];
                    $session->setToLang($this->_toLang);
                    return $this->_toLang;
                }
            }
        }

        private function _getRandomUserAgent ( ) {
            $userAgents = array (
                "Mozilla/5.0 (Windows; U; Windows NT 6.0; fr; rv:1.9.1b1) Gecko/20081007 Firefox/3.1b1",
                "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.1) Gecko/2008070208 Firefox/3.0.0",
                "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US) AppleWebKit/525.19 (KHTML, like Gecko) Chrome/0.4.154.18 Safari/525.19",
                "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US) AppleWebKit/525.13 (KHTML, like Gecko) Chrome/0.2.149.27 Safari/525.13",
                "Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 5.1; Trident/4.0; .NET CLR 1.1.4322; .NET CLR 2.0.50727; .NET CLR 3.0.04506.30)",
                "Mozilla/4.0 (compatible; MSIE 7.0b; Windows NT 5.1; .NET CLR 1.1.4322; .NET CLR 2.0.40607)",
                "Mozilla/4.0 (compatible; MSIE 7.0b; Windows NT 5.1; .NET CLR 1.1.4322)",
                "Mozilla/4.0 (compatible; MSIE 7.0b; Windows NT 5.1; .NET CLR 1.0.3705; Media Center PC 3.1; Alexa Toolbar; .NET CLR 1.1.4322; .NET CLR 2.0.50727)",
                "Mozilla/45.0 (compatible; MSIE 6.0; Windows NT 5.1)",
                "Mozilla/4.08 (compatible; MSIE 6.0; Windows NT 5.1)",
                "Mozilla/4.01 (compatible; MSIE 6.0; Windows NT 5.1)"
                );

            srand((double)microtime()*1000000);
            return $userAgents[rand(0,count($userAgents)-1)];
        }

        private function _getContent ($url) {
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_USERAGENT, $this->_getRandomUserAgent());
            curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);

            $output = curl_exec($ch);
            $info = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            curl_close($ch);
            if ($output === false || $info != 200) {
              $output = null;
            }
            return $output;

        }

        private function _translate($text, $from, $to) {
            $session    = $this->getSession();
            $langCache  = $session->getLangCache();
            $key        = $this->_getKey($text);
            
            if (isset($langCache[$key])) {
                return $langCache[$key];
            }
            
            $f      = $this->_getContent("http://translate.google.com/translate_t?text=" . urlencode($text) . "&langpair=$from|$to");
            $x      = strstr($f, '<span id=result_box');		
            $arr    = explode('<script',$x);
            $arr    = explode('Undo edits',$arr[0]);
            
            $this->_setToCache(strip_tags($arr[0]), $key);
            
            return(strip_tags($arr[0]));
        }

        public function getText($text) {
            return $this->_translate($text, $this->_getBaseLang(), $this->_getToLang());
        }
        
        private function _getFromCache($key) {
            $session    = $this->getSession();
            $langCache  = $session->getLangCache();
            
//            return ((isset($langCache[$key])) ? $langCache[$key] : null);
            return ((isset($langCache[$this->_getToLang()][$key])) ? $langCache[$this->_getToLang()][$key] : null);
        }
        
        private function _setToCache($text, $key) {
            $session    = $this->getSession();
            $langCache  = $session->getLangCache();
            
            $langCache[$this->_getToLang()][$key] = $text;
            $session->setLangCache($langCache);
        }
        
        public function clearCache() {
            $session    = $this->getSession();
            $langCache  = $session->getLangCache();
            $session->setLangCache($langCache);
        }
        
        private function _getKey($text) {
            return md5($text);
        }
        
        function cacheToFile($lang) {
            $session    = $this->getSession();
            $langCache  = $session->getLangCache();
            
            if (!file_exists(Api::getPath('tmp').'i18n/')) {
                mkdir(Api::getPath('tmp').'i18n/');
            }
            
            $file   = Api::getPath('tmp').'i18n/'.$lang.'.yml';
            $data   = Yaml_SfYaml::dump($langCache[$lang]);
            $result = file_put_contents($file, $data);
        }
        
        function allCacheToFile() {
            $session    = $this->getSession();
            $langCache  = $session->getLangCache();
            
            if (!file_exists(Api::getPath('tmp').'i18n/')) {
                mkdir(Api::getPath('tmp').'i18n/');
            }
            
            foreach ($langCache as $lang => $langData) {
                $file   = Api::getPath('tmp').'i18n/'.$lang.'.yml';
                $data   = Yaml_SfYaml::dump($langData);
                file_put_contents($file, $data);
            }
        }
    }

?> 
