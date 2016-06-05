<?php
    session_start();

    class core {
        public static $views = [];
        public static $title = '';
        public static $description = '';
        public static $content = '';
        public static $layout = 'LAYOUT';
        private $data;
        private $url;
        private $error;
        private $success;
        private static $modules = ['create', 'edit', 'ajax', 'module', 'delete', 'clean', 'export', 'mode', 'config', 'logout'];

        public function __construct() {
            $this -> data = json_decode(file_get_contents('data.json'), true);
            $this -> url = empty($_SERVER['QUERY_STRING']) ? $this -> getData(['config', 'index']) : $_SERVER['QUERY_STRING'];
            $this -> url = helper::filter($this -> url, helper::URL);
            $this -> url = explode('/', $this -> url);
            $this -> error = empty($_SESSION['ERROR']) ? '' : $_SESSION['ERROR'];
            $this -> success = empty($_SESSION['SUCCESS']) ? '' : $_SESSION['SUCCESS'];
        }

        public static function autoload($className) {
            $className = substr($className, 0, -3);
            $classPath = 'modules/' . $className . '/' . $className . '.php';
            if(is_readable($classPath)) {
                require $classPath;
            }
        }

        public function getData($keys = null) {
            if($keys === null) {
                return $this -> data;
            }
            elseif(!is_array($keys)) {
                $keys = [$keys];
            }
            $data = $this -> data;
            foreach($keys as $key) {
                if(empty($data[$key])) {
                    return false;
                }
                else {
                    $data = $data[$key];
                }
            }
            return $data;
        }

        public function setData($keys) {
            if(!template::$notices) {
                switch(count($keys)) {
                    case 2 :
                        $this -> data[$keys[0]] = $keys[1];
                        break;
                    case 3:
                        $this -> data[$keys[0]][$keys[1]] = $keys[2];
                        break;
                    case 4:
                        $this -> data[$keys[0]][$keys[1]][$keys[2]] = $keys[3];
                        break;
                }
            }
        }

        public function removeData($keys) {
            if(!template::$notices) {
                if(!is_array($keys)) {
                    $keys = [$keys];
                }
                switch(count($keys)) {
                    case 1 :
                        unset($this -> data[$keys[0]]);
                        break;
                    case 2:
                        unset($this -> data[$keys[0]][$keys[1]]);
                        break;
                    case 3:
                        unset($this -> data[$keys[0]][$keys[1]][$keys[2]]);
                        break;
                    case 4:
                        unset($this -> data[$keys[0]][$keys[1]][$keys[2]][$keys[3]]);
                        break;
                }
            }
        }

        public function saveData() {
            if(!template::$notices) {
                foreach($it as $file) {
                    if($file -> isFile()) {
                        if($this -> getUrl(0) === explode('_', $file -> getBasename('.html'))[0]) {
                            unlink($file -> getPathname());
                        }
                    }
                }
                file_put_contents('data.json', json_encode($this -> getData()));
            }
        }

        public function getUrl($key = null, $splice = true) {
            $url = $this -> url;
            if($key === null) {
                return implode('/', $url);
            }
            else {
                if($splice AND (in_array($url[0], self::$modules))) {
                    array_splice($url, 0, 1);
                }
                return empty($url[$key]) ? '' : helper::filter($url[$key], helper::URL);
            }

        }

        public function getCookie() {
            return isset($_COOKIE['PASSWORD']) ? $_COOKIE['PASSWORD'] : '';
        }

        public function setCookie($password, $time) {
            setcookie('PASSWORD', helper::filter($password, helper::PASSWORD), $time);
        }

        public function removeCookie() {
            setcookie('PASSWORD');
        }

        public function getNotification() {
            if(template::$notices) {
                return template::div(['id' => 'notification', 'class' => 'error', 'text' => 'Impossible de soumettre le formulaire, car il contient des erreurs !']);
            }
            elseif($this -> error) {
                unset($_SESSION['ERROR']);
                return template::div(['id' => 'notification', 'class' => 'error', 'text' => $this -> error]);
            }
            elseif($this -> success) {
                unset($_SESSION['SUCCESS']);
                return template::div(['id' => 'notification', 'class' => 'success', 'text' => $this -> success]);
            }
        }

        public function setNotification($notification, $error = false) {
            if(!template::$notices) {
                $_SESSION[$error ? 'ERROR' : 'SUCCESS'] = $notification;
            }
        }

        public function getMode() {
            return empty($_SESSION['MODE']) ? '' : 'edit/';
        }

        public function setMode($mode) {
            $_SESSION['MODE'] = $mode;
        }

        public function getPost($keys, $filter = null) {
            if(!is_array($keys)) {
                if(empty($_POST[$keys])) {
                    template::getRequired($keys);
                }
                $keys = [$keys];
            }
            $post = $_POST;
            foreach($keys as $key) {
                if(isset($post[$key])) {
                    $post = $post[$key];
                }
                else {
                    $post = '';
                    break;
                }
            }
            return ($filter !== null) ? helper::filter($post, $filter) : $post;
        }

        public function router() {
            if(in_array($this -> getUrl(0, false), self::$modules)) {
                if($this -> getData(['config', 'password']) === $this -> getCookie()) {
                    $method = $this -> getUrl(0, false);
                    $this -> $method();
                    if($this -> getUrl(0, false) !== 'config') {
                        $this -> setMode(true);
                    }
                }
                else {
                    $this -> login();
                }
            }
            elseif($this -> getData(['pages', $this -> getUrl(0, false)])) {
                if(!$this -> getCookie() AND self::$layout === 'LAYOUT') {
                    $url = str_replace('/', '_', $this -> getUrl());
                }
                if($this -> getData(['pages', $this -> getUrl(0), 'module'])) {
                    $module = $this -> getData(['pages', $this -> getUrl(0), 'module']) . 'Mod';
                    $module = new $module;
                    $method = in_array($this -> getUrl(1), $module::$views) ? $this -> getUrl(1) : 'index';
                    $module -> $method();
                }
                $this -> setMode(false);
                self::$title = $this -> getData(['pages', $this -> getUrl(0, false), 'title']);
                self::$description = $this -> getData(['pages', $this -> getUrl(0, false), 'description']);
                self::$content = $this -> getData(['pages', $this -> getUrl(0, false), 'content']) . self::$content;
            }
            if(!self::$content) {
                header("HTTP/1.0 404 Not Found");
                self::$title = 'Erreur 404';
                self::$content = '<p>Page introuvable !</p>';
            }
            switch(self::$layout) {
                case 'LAYOUT':
                    if(!self::$description) {
                        self::$description = $this -> getData(['config', 'description']);
                    }
                    require 'core/layout.html';
                    break;
                case 'JSON':
                    echo json_encode(self::$content);
                    break;
                case 'BLANK':
                    echo self::$content;
                    break;
            }
        }

        public function panel() {
            if($this -> getCookie() === $this -> getData(['config', 'password'])) {
                $li = '<li>';
                $li .= '<select onchange="$(location).attr(\'href\', $(this).val());">';
                if($this -> getUrl(0, false) === 'config') {
                    $li .= '<option value="">Choisissez une page</option>';
                }
                $pages = helper::arrayCollumn($this -> getData('pages'), 'title', 'SORT_ASC', true);
                foreach($pages as $pageKey => $pageTitle) {
                    $current = ($pageKey === $this -> getUrl(0)) ? ' selected' : false;
                    $li .= '<option value="' . helper::baseUrl() . $this -> getMode() . $pageKey . '"' . $current . '>' . $pageTitle . '</option>';
                }
                $li .= '</select>';
                $li .= '</li>';
                $li .= '<li>';
                $li .= '<a href="' . helper::baseUrl() . 'create">Créer une page</a>';
                $li .= '</li>';
                if($this -> getUrl(0, false) !== 'config') {
                    $li .= '<li>';
                    $li .= '<a href="' . helper::baseUrl() . 'mode/' . $this->getUrl(null, false) . '"' . ($this->getMode() ? ' class="edit"' : '') . '>Mode édition</a>';
                    $li .= '</li>';
                };
                $li .= '<li>';
                $li .= '<a href="' . helper::baseUrl() . 'config">Configuration</a>';
                $li .= '</li>';
                $li .= '<li>';
                $li .= '<a href="' . helper::baseUrl() . 'logout" onclick="return confirm(\'' . 'Êtes-vous sûr de vouloir vous déconnecter ?' . '\');">';
                $li .= 'Déconnexion';
                $li .= '</a>';
                $li .= '</li>';
                return '<ul id="panel">' . $li . '</ul>';
            }
        }

        public function menu() {
            $edit = ($this -> getCookie() === $this -> getData(['config', 'password'])) ? $this -> getMode() : false;
            $pageKeys = helper::arrayCollumn($this -> getData('pages'), 'position', 'SORT_ASC');
            $items = false;
            foreach($pageKeys as $pageKey) {
                $current = ($pageKey === $this -> getUrl(0)) ? ' class="current"' : false;
                $blank = ($this -> getData(['pages', $pageKey, 'blank']) AND !$this -> getMode()) ? ' target="_blank"' : false;
                $items .= '<li><a href="' . helper::baseUrl() . $edit . $pageKey . '"' . $current . $blank . '>' . $this -> getData(['pages', $pageKey, 'title']) . '</a></li>';
            }
            return $items;
        }

        public function js() {
            $module = 'modules/' . $this -> getData(['pages', $this -> getUrl(0), 'module']) . '/' . $this -> getData(['pages', $this -> getUrl(0), 'module']) . '.js';
            if(is_file($module)) {
                return '<script src="' . $module . '"></script>';
            }
        }

        public function css() {
            $module = 'modules/' . $this -> getData(['pages', $this -> getUrl(0), 'module']) . '/' . $this -> getData(['pages', $this -> getUrl(0), 'module']) . '.css';
            if(is_file($module)) {
                return '<link rel="stylesheet" href="' . $module . '.css">';
            }
        }

        public function create() {
            $title = 'Nouvelle page';
            $key = helper::increment(helper::filter($title, helper::URL), $this -> getData('pages'));
            $this -> setData(['pages', $key, ['title' => $title, 'description' => false, 'position' => '0', 'blank' => false, 'module' => false, 'content' => '<p>Contenu de la page.</p>']]);
            $this -> saveData();
            $this -> setNotification('Nouvelle page créée avec succès !');
            helper::redirect('edit/' . $key);
        }

        public function edit() {
            if(!$this -> getData(['pages', $this -> getUrl(0)])) {
                return false;
            }
            elseif($this -> getPost('submit')) {
                $key = $this -> getPost('title') ? $this -> getPost('title', helper::URL) : $this -> getUrl(0);
                $module = $this -> getData(['pages', $this -> getUrl(0), 'module']);
                if($key !== $this -> getUrl(0)) {
                    $key = helper::increment($key, $this -> getData('pages'));
                    $key = helper::increment($key, self::$modules);
                    $this -> removeData(['pages', $this -> getUrl(0)]);
                    $this -> setData([$key, $this -> getData($this -> getUrl(0))]);
                    $this -> removeData($this -> getUrl(0));
                    if($this -> getData(['config', 'index']) === $this -> getUrl(0)) {
                        $this -> setData(['config', 'index', $key]);
                    }
                }
                $position = $this -> getPost('position', helper::INT);
                if($position AND $position !== $this -> getData(['pages', $this -> getUrl(0), 'position'])) {
                    $newPosition = $position;
                    $pages = array_flip(helper::arrayCollumn($this -> getData('pages'), 'position', 'SORT_ASC', true));
                    foreach($pages as $pagePosition => $pageKey) {
                        if($pagePosition >= $position) {
                            $newPosition++;
                            $this -> setData(['pages', $pageKey, 'position', $newPosition]);
                        }
                    }
                }
                $this -> setData(['pages', $key, ['title' => $this -> getPost('title', helper::STRING), 'description' => $this -> getPost('description', helper::STRING), 'position' => $position, 'blank' => $this -> getPost('blank', helper::BOOLEAN), 'module' => $module, 'content' => $this -> getPost('content')]]);
                if($key !== $this -> getUrl(0)) {
                    $this -> removeData(['pages', $this -> getUrl(0)]);
                }
                $this -> saveData($key);
                $this -> setNotification('Page modifiée avec succès !');
                helper::redirect('edit/' . $key);
            }
            $listPages = ['Ne pas afficher', 'Au début'];
            $selected = 0;
            $pagePositionPrevious = 1;
            $pages = array_flip(helper::arrayCollumn($this -> getData('pages'), 'position', 'SORT_ASC', true));
            foreach($pages as $pagePosition => $pageKey) {
                if($pageKey === $this -> getUrl(0)) {
                    $selected = $pagePositionPrevious;
                }
                else {
                    $listPages[$pagePosition + 1] = 'Après "' . $this -> getData(['pages', $pageKey, 'title']) . '"';
                    $pagePositionPrevious = $pagePosition + 1;
                }
            }
            self::$title = $this -> getData(['pages', $this -> getUrl(0), 'title']);
            self::$content = template::openForm() . template::openRow() . template::text('title', ['label' => 'Titre de la page', 'value' => $this -> getData(['pages', $this -> getUrl(0), 'title']), 'required' => true]) . template::newRow() . template::select('position', $listPages, ['label' => 'Position dans le menu', 'selected' => $selected]) . template::newRow() . template::textarea('content', ['value' => $this -> getData(['pages', $this -> getUrl(0), 'content']), 'class' => 'editor']) . template::newRow() . template::textarea('description', ['label' => 'Description de la page', 'value' => $this -> getData(['pages', $this -> getUrl(0), 'description'])]) . template::newRow() . template::hidden('key', ['value' => $this -> getUrl(0)]) . template::hidden('oldModule', ['value' => $this -> getData(['pages', $this -> getUrl(0), 'module'])]) . template::select('module', helper::listModules('Aucun module'), ['label' => 'Inclure le module', 'selected' => $this -> getData(['pages', $this -> getUrl(0), 'module']), 'col' => 10]) . template::button('admin', ['value' => 'Administrer', 'href' => helper::baseUrl() . 'module/' . $this -> getUrl(0), 'disabled' => $this -> getData(['pages', $this -> getUrl(0), 'module']) ? '' : 'disabled', 'col' => 2]) . template::newRow() . template::checkbox('blank', true, 'Ouvrir dans un nouvel onglet en mode public', ['checked' => $this -> getData(['pages', $this -> getUrl(0), 'blank'])]) . template::newRow() . template::button('delete', ['value' => 'Supprimer', 'href' => helper::baseUrl() . 'delete/' . $this -> getUrl(0), 'onclick' => 'return confirm(\'' . 'Êtes-vous sûr de vouloir supprimer cette page ?' . '\');', 'col' => 2, 'offset' => 8]) . template::submit('submit', ['col' => 2]) . template::closeRow() . template::closeForm();
        }

        public function ajax() {
            if(!$this -> getData(['pages', $this -> getUrl(0)])) {
                return false;
            }
            if($this -> getPost('module') !== $this -> getData(['pages', $this -> getUrl(0), 'module'])) {
                $this -> removeData($this -> getUrl(0));
            }
            $this -> setData(['pages', $this -> getUrl(0), ['title' => $this -> getData(['pages', $this -> getUrl(0), 'title']), 'description' => $this -> getData(['pages', $this -> getUrl(0), 'description']), 'position' => $this -> getData(['pages', $this -> getUrl(0), 'position']), 'blank' => $this -> getData(['pages', $this -> getUrl(0), 'blank']), 'module' => $this -> getPost('module', helper::STRING), 'content' => $this -> getData(['pages', $this -> getUrl(0), 'content'])]]);
            $this -> saveData();
            self::$layout = 'JSON';
            self::$content = true;
        }

        public function module() {
            if(!$this -> getData(['pages', $this -> getUrl(0), 'module'])) {
                return false;
            }
            $module = $this -> getData(['pages', $this -> getUrl(0), 'module']) . 'Adm';
            $module = new $module;
            $method = in_array($this -> getUrl(1), $module::$views) ? $this -> getUrl(1) : 'index';
            $module -> $method();
            self::$title = $this -> getData(['pages', $this -> getUrl(0), 'title']);
        }

        public function delete() {
            if(!$this -> getData(['pages', $this -> getUrl(0)])) {
                return false;
            }
            elseif($this -> getUrl(0) === $this -> getData(['config', 'index'])) {
                $this -> setNotification('Impossible de supprimer la page d\'accueil !', true);
            }
            else {
                $this -> removeData(['pages', $this -> getUrl(0)]);
                $this -> removeData($this -> getUrl(0));
                $this -> saveData();
                $this -> setNotification('Page supprimée avec succès !');
            }
            helper::redirect('edit/' . $this -> getData(['config', 'index']));
        }

        public function export() {
            header('Content-disposition: attachment; filename=data.json');
            header('Content-type: application/json');
            self::$content = $this -> getData();
            self::$layout = 'JSON';
        }

        public function mode() {
            if($this -> getData(['pages', $this -> getUrl(0)])) {
                $url = 'edit/' . $this -> getUrl(0);
            }
            elseif(in_array($this -> getUrl(0), ['edit', 'module'])) {
                $url = $this -> getUrl(1);
            }
            else {
                $url = $this -> getUrl(0);
            }
            helper::redirect($url);
        }

        public function config() {
            if($this -> getPost('submit')) {
                if($this -> getPost('password')) {
                    if($this -> getPost('password') === $this -> getPost('confirm')) {
                        $password = $this -> getPost('password', helper::PASSWORD);
                    }
                    else {
                        $password = $this -> getData(['config', 'password']);
                        template::$notices['confirm'] = 'La confirmation du mot de passe ne correspond pas au mot de passe';
                    }
                }
                else {
                    $password = $this -> getData(['config', 'password']);
                }
                $this -> setData(['config', ['title' => $this -> getPost('title', helper::STRING), 'description' => $this -> getPost('description', helper::STRING), 'password' => $password, 'index' => $this -> getPost('index', helper::STRING)]]);
                $this -> saveData(true);
                $this -> setNotification('Configuration enregistrée avec succès !');
                helper::redirect($this -> getUrl());
            }
            self::$title = 'Configuration';
            self::$content = template::openForm() . template::openRow() . template::text('title', ['label' => 'Titre du site', 'required' => 'required', 'value' => $this -> getData(['config', 'title'])]) . template::newRow() . template::textarea('description', ['label' => 'Description du site', 'required' => 'required', 'value' => $this -> getData(['config', 'description'])]) . template::newRow() . template::password('password', ['label' => 'Nouveau mot de passe', 'col' => 6]) . template::password('confirm', ['label' => 'Confirmation du mot de passe', 'col' => 6]) . template::newRow() . template::select('index', helper::arrayCollumn($this -> getData('pages'), 'title', 'SORT_ASC', true), ['label' => 'Page d\'accueil', 'required' => 'required', 'selected' => $this -> getData(['config', 'index'])]) . template::button('export', ['value' => 'Exporter les données', 'href' => helper::baseUrl() . 'export', 'col' => 3]) . template::submit('submit', ['col' => 2]) . template::closeRow() . template::closeForm();
        }

        public function login() {
            if($this -> getPost('submit')) {
                if($this -> getPost('password', helper::PASSWORD) === $this -> getData(['config', 'password'])) {
                    $time = $this -> getPost('time') ? 0 : time() + 10 * 365 * 24 * 60 * 60;
                    $this -> setCookie($this -> getPost('password'), $time);
                }
                else {
                    $this -> setNotification('Mot de passe incorrect !', true);
                }
                helper::redirect($this -> getUrl());
            }
            self::$title = 'Connexion';
            self::$content = template::openForm() . template::openRow() . template::password('password', ['required' => 'required', 'col' => 4]) . template::newRow() . template::checkbox('time', true, 'Me connecter automatiquement à chaque visite') . template::newRow() . template::submit('submit', ['value' => 'Me connecter', 'col' => 2]) . template::closeRow() . template::closeForm();
        }

        public function logout() {
            $this -> removeCookie();
            helper::redirect('./', false);
        }

    }

    class helper {
        const PASSWORD = 'FILTER_SANITIZE_PASSWORD';
        const BOOLEAN = 'FILTER_SANITIZE_BOOLEAN';
        const URL = 'FILTER_SANITIZE_URL';
        const STRING = FILTER_SANITIZE_STRING;
        const FLOAT = FILTER_SANITIZE_NUMBER_FLOAT;
        const INT = FILTER_SANITIZE_NUMBER_INT;

        public static function baseUrl($queryString = true) {
            $currentPath = $_SERVER['PHP_SELF'];
            $pathInfo = pathinfo($currentPath);
            $hostName = $_SERVER['HTTP_HOST'];
            $protocol = strtolower(substr($_SERVER["SERVER_PROTOCOL"], 0, 5)) == 'https://' ? 'https://' : 'http://';
            return $protocol . $hostName . rtrim($pathInfo['dirname'], ' \/') . '/';
        }

        public static function filter($text, $filter) {
            switch($filter) {
                case self::PASSWORD:
                    $text = sha1($text);
                    break;
                case self::BOOLEAN:
                    $text = empty($text) ? false : true;
                    break;
                case self::URL:
                    $search = explode(',', 'á,à,â,ä,ã,å,ç,é,è,ê,ë,í,ì,î,ï,ñ,ó,ò,ô,ö,õ,ú,ù,û,ü,ý,ÿ, ');
                    $replace = explode(',', 'a,a,a,a,a,a,c,e,e,e,e,i,i,i,i,n,o,o,o,o,o,u,u,u,u,y,y,-');
                    $text = str_replace($search, $replace, mb_strtolower($text, 'UTF-8'));
                    break;
                default:
                    $text = filter_var($text, $filter);
            }
            return get_magic_quotes_gpc() ? stripslashes($text) : $text;
        }

        public static function increment($key, $array) {
            if(empty($array)) {
                return $key;
            }
            else {
                if(is_numeric($key)) {
                    $newKey = $key;
                    while(array_key_exists($newKey, $array) OR in_array($newKey, $array)) {
                        $newKey++;
                    }
                }
                else {
                    $i = 2;
                    $newKey = $key;
                    while(array_key_exists($newKey, $array) OR in_array($newKey, $array)) {
                        $newKey = $key . '-' . $i;
                        $i++;
                    }
                }
                return $newKey;
            }
        }

        public static function arrayCollumn($array, $columnKey, $sort = '', $keep = false) {
            $row = [];
            if(!empty($array)) {
                foreach($array as $key => $value) {
                    if($value[$columnKey]) {
                        $row[$key] = $value[$columnKey];
                    }
                }
                switch($sort) {
                    case 'SORT_ASC':
                        asort($row);
                        break;
                    case 'SORT_DESC':
                        arsort($row);
                        break;
                }
                $row = $keep ? $row : array_keys($row);
            }
            return $row;
        }

        public static function pagination($array, $url) {
            $url = explode('/', $url);
            $urlPagination = is_numeric(end($url)) ? array_pop($url) : 1;
            $urlCurrent = implode('/', $url);
            $nbElements = count($array);
            $nbPage = ceil($nbElements / 10);
            $currentPage = is_numeric($urlPagination) ? (int) $urlPagination : 1;
            $firstElement = ($currentPage - 1) * 10;
            $lastElement = $firstElement + 10;
            $lastElement = ($lastElement > $nbElements) ? $nbElements : $lastElement;
            $pages = false;
            for($i = 1; $i <= $nbPage; $i++) {
                $disabled = ($i === $currentPage) ? ' class="disabled"' : false;
                $pages .= '<a href="' . helper::baseUrl() . $urlCurrent . '/' . $i . '"' . $disabled . '>' . $i . '</a>';
            }
            return ['first' => $firstElement, 'last' => $lastElement, 'pages' => '<div class="pagination">' . $pages . '</div>'];
        }

        public static function listModules($default = false) {
            $modules = [];
            if($default) {
                $modules[''] = $default;
            }
            $it = new DirectoryIterator('modules/');
            foreach($it as $dir) {
                if($dir -> isDir() AND $dir -> getBasename() !== '.' AND $dir -> getBasename() !== '..') {
                    $module = $dir -> getBasename() . 'Adm';
                    $module = new $module;
                    $modules[$dir -> getBasename()] = $module::$name;
                }
            }
            return $modules;
        }

        public static function redirect($url, $baseUrl = true) {
            if(template::$notices) {
                template::$before = $_POST;
            }
            else {
                header('Status: 301 Moved Permanently', false, 301);
                header('Location: ' . ($baseUrl ? self::baseUrl() : false) . $url);
                exit();
            }
        }
    }

    class template {
        public static $notices = [];
        public static $before = [];

        public static function getRequired($key) {
            if(!empty($_SESSION['REQUIRED']) AND array_key_exists($key . '.' . md5($_SERVER['QUERY_STRING']), $_SESSION['REQUIRED'])) {
                self::$notices[$key] = 'Ce champ est requis';
            }
        }

        private static function setRequired($id, $attributes) {
            if(!empty($_SESSION['REQUIRED']) AND array_key_exists($id . '.' . md5($_SERVER['QUERY_STRING']), $_SESSION['REQUIRED'])) {
                unset($_SESSION['REQUIRED'][$id . '.' . md5($_SERVER['QUERY_STRING'])]);
            }
            if(!empty($attributes['required']) AND (empty($_SESSION['REQUIRED']) OR !array_key_exists($id . '.' . md5($_SERVER['QUERY_STRING']), $_SESSION['REQUIRED']))) {
                $_SESSION['REQUIRED'][$id . '.' . md5($_SERVER['QUERY_STRING'])] = true;
            }
        }

        private static function getNotice($id) {
            return '<div class="notice">' . self::$notices[$id] . '</div>';
        }

        private static function getBefore($nameId) {
            return array_key_exists($nameId, self::$before) ? self::$before[$nameId] : null;
        }

        private static function sprintAttributes(array $array = [], array $exclude = []) {
            $exclude = array_merge(['col', 'offset', 'label', 'selected', 'required'], $exclude);
            $attributes = [];
            foreach($array as $key => $value) {
                if($value AND !in_array($key, $exclude)) {
                    $attributes[] = sprintf('%s="%s"', $key, $value);
                }
            }
            return implode(' ', $attributes);
        }

        public static function openRow() {
            return '<div class="row">';
        }

        public static function newRow() {
            return '</div><div class="row">';
        }

        public static function closeRow() {
            return '</div>';
        }

        public static function openForm($nameId = 'form', $attributes = []) {
            $attributes = array_merge(['id' => $nameId, 'name' => $nameId, 'target' => '', 'action' => '', 'method' => 'post', 'enctype' => '', 'class' => ''], $attributes);
            return sprintf('<form %s>', self::sprintAttributes($attributes));
        }

        public static function closeForm() {
            return '</form>';
        }

        public static function title($text) {
            return '<h3>' . $text . '</h3>';
        }

        public static function subTitle($text) {
            return '<h4>' . $text . '</h4>';
        }

        public static function div($attributes = []) {
            $attributes = array_merge(['id' => '', 'text' => '', 'class' => '', 'data-1' => '', 'data-2' => '', 'data-3' => '', 'col' => 0, 'offset' => 0], $attributes);
            return sprintf('<div class="col%s offset%s %s" %s>%s</div>', $attributes['col'], $attributes['offset'], $attributes['class'], self::sprintAttributes($attributes, ['class', 'text']), $attributes['text']);
        }

        public static function label($for, $text, array $attributes = []) {
            $attributes = array_merge(['for' => $for,'class' => ''], $attributes);
            return sprintf('<label %s>%s</label>', self::sprintAttributes($attributes), $text);
        }

        public static function hidden($nameId, array $attributes = []) {
            $attributes = array_merge(['id' => $nameId, 'name' => $nameId, 'value' => '', 'class' => ''], $attributes);
            if(($value = self::getBefore($nameId)) !== null) {
                $attributes['value'] = $value;
            }
            $html = sprintf('<input type="hidden" %s>', self::sprintAttributes($attributes));
            return $html;
        }

        public static function text($nameId, array $attributes = []) {
            $attributes = array_merge(['id' => $nameId, 'name' => $nameId, 'value' => '', 'placeholder' => '', 'disabled' => '', 'readonly' => '', 'required' => '', 'label' => '', 'class' => '', 'col' => 12, 'offset' => 0], $attributes);
            self::setRequired($nameId, $attributes);
            if(($value = self::getBefore($nameId)) !== null) {
                $attributes['value'] = $value;
            }
            $html = '<div class="col' . $attributes['col'] . ' offset' . $attributes['offset'] . '">';
            if($attributes['label']) {
                $html .= self::label($nameId, $attributes['label']);
            }
            if(!empty(self::$notices[$nameId])) {
                $html .= self::getNotice($nameId);
                $attributes['class'] .= ' notice';
            }
            $html .= sprintf('<input type="text" %s>', self::sprintAttributes($attributes)) . '</div>';
            return $html;
        }

        public static function textarea($nameId, array $attributes = []) {
            $attributes = array_merge(['id' => $nameId, 'name' => $nameId, 'value' => '', 'disabled' => '', 'readonly' => '', 'required' => '', 'label' => '', 'class' => '', 'col' => 12, 'offset' => 0], $attributes);
            self::setRequired($nameId, $attributes);
            if(($value = self::getBefore($nameId)) !== null) {
                $attributes['value'] = $value;
            }
            $html = '<div class="col' . $attributes['col'] . ' offset' . $attributes['offset'] . '">';
            if($attributes['label']) {
                $html .= self::label($nameId, $attributes['label']);
            }
            if(!empty(self::$notices[$nameId])) {
                $html .= self::getNotice($nameId);
                $attributes['class'] .= ' notice';
            }
            $html .= sprintf('<textarea %s>%s</textarea>', self::sprintAttributes($attributes, ['value']), $attributes['value']) . '</div>';
            return $html;
        }

        public static function password($nameId, array $attributes = []) {
            $attributes = array_merge(['id' => $nameId, 'name' => $nameId, 'placeholder' => '', 'disabled' => '', 'readonly' => '', 'required' => '', 'label' => '', 'class' => '', 'col' => 12, 'offset' => 0], $attributes);
            self::setRequired($nameId, $attributes);
            $html = '<div class="col' . $attributes['col'] . ' offset' . $attributes['offset'] . '">';
            if($attributes['label']) {
                $html .= self::label($nameId, $attributes['label']);
            }
            if(!empty(self::$notices[$nameId])) {
                $html .= self::getNotice($nameId);
                $attributes['class'] .= ' notice';
            }
            $html .= sprintf('<input type="password" %s>', self::sprintAttributes($attributes)) . '</div>';
            return $html;
        }

        public static function select($nameId, array $options, array $attributes = []) {
            $attributes = array_merge(['id' => $nameId, 'name' => $nameId, 'selected' => '', 'disabled' => '', 'required' => '', 'label' => '', 'class' => '', 'col' => 12, 'offset' => 0], $attributes);
            self::setRequired($nameId, $attributes);
            if($selected = self::getBefore($nameId)) {
                $attributes['selected'] = $selected;
            }
            $html = '<div class="col' . $attributes['col'] . ' offset' . $attributes['offset'] . '">';
            if($attributes['label']) {
                $html .= self::label($nameId, $attributes['label']);
            }
            if(!empty(self::$notices[$nameId])) {
                $html .= self::getNotice($nameId);
                $attributes['class'] .= ' notice';
            }
            $html .= sprintf('<select %s>', self::sprintAttributes($attributes));
            foreach($options as $value => $text) {
                $html .= sprintf('<option value="%s"%s>%s</option>', $value, $attributes['selected'] === $value ? ' selected' : '', $text);
            }
            $html .= '</select></div>';
            return $html;
        }

        public static function checkbox($nameId, $value, $label, array $attributes = []) {
            $attributes = array_merge(['checked' => '', 'disabled' => '', 'required' => '', 'class' => '', 'col' => 12, 'offset' => 0], $attributes);
            self::setRequired($nameId, $attributes);
            $html = '<div class="col' . $attributes['col'] . ' offset' . $attributes['offset'] . '">';
            if(!empty(self::$notices[$nameId])) {
                $html .= self::getNotice($nameId);
                $attributes['class'] .= ' notice';
            }
            $html .= sprintf('<input type="checkbox" id="%s" name="%s" value="%s" %s>', $nameId . '_' . $value, $nameId . '[]', $value, self::sprintAttributes($attributes)) . self::label($nameId . '_' . $value, $label) . '</div>';
            return $html;
        }

        public static function radio($nameId, $value, $label, array $attributes = []) {
            $attributes = array_merge(['checked' => '', 'disabled' => '', 'required' => '', 'class' => '', 'col' => 12, 'offset' => 0], $attributes);
            self::setRequired($nameId, $attributes);
            $html = '<div class="col' . $attributes['col'] . ' offset' . $attributes['offset'] . '">';
            if(!empty(self::$notices[$nameId])) {
                $html .= self::getNotice($nameId);
                $attributes['class'] .= ' notice';
            }
            $html .= sprintf('<input type="radio" id="%s" name="%s" value="%s" %s>', $nameId . '_' . $value, $nameId . '[]', $value, self::sprintAttributes($attributes)) . self::label($nameId . '_' . $value, $label) . '</div>';
            return $html;
        }

        public static function submit($nameId, array $attributes = []) {
            $attributes = array_merge(['id' => $nameId, 'name' => $nameId, 'value' => 'Enregistrer', 'disabled' => '', 'class' => '', 'col' => 12, 'offset' => 0], $attributes);
            $html = '<div class="col' . $attributes['col'] . ' offset' . $attributes['offset'] . '">' . sprintf('<input type="submit" value="%s" %s>', $attributes['value'], self::sprintAttributes($attributes, ['value'])) . '</div>';
            return $html;
        }

        public static function button($nameId, array $attributes = []) {
            $attributes = array_merge(['id' => $nameId, 'name' => $nameId, 'value' => 'Bouton', 'href' => 'javascript:void(0);', 'target' => '', 'onclick' => '', 'disabled' => '', 'class' => '', 'col' => 12, 'offset' => 0], $attributes);
            $html = '<div class="col' . $attributes['col'] . ' offset' . $attributes['offset'] . '">' . sprintf('<a %s class="button %s %s">%s</a>', self::sprintAttributes($attributes, ['value', 'class', 'disabled']), $attributes['disabled'] ? 'disabled' : '', $attributes['class'], $attributes['value']) . '</div>';
            return $html;
        }

        public static function background($text, array $attributes = []) {
            $attributes = array_merge(['class' => '', 'col' => 12, 'offset' => 0], $attributes);
            $html = '<div class="col' . $attributes['col'] . ' offset' . $attributes['offset'] . '"><div class="background ' . $attributes['class']. '">' . $text . '</div></div>';
            return $html;
        }
    }
?>