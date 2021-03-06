<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @copyright   Copyright (c) 2014 X.commerce, Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * URL
 *
 * Properties:
 *
 * - request
 *
 * - relative_url: true, false
 * - type: 'link', 'skin', 'js', 'media'
 * - scope: instanceof \Magento\Url\ScopeInterface
 * - secure: true, false
 *
 * - scheme: 'http', 'https'
 * - user: 'user'
 * - password: 'password'
 * - host: 'localhost'
 * - port: 80, 443
 * - base_path: '/dev/magento/'
 * - base_script: 'index.php'
 *
 * - scopeview_path: 'scopeview/'
 * - route_path: 'module/controller/action/param1/value1/param2/value2'
 * - route_name: 'module'
 * - controller_name: 'controller'
 * - action_name: 'action'
 * - route_params: array('param1'=>'value1', 'param2'=>'value2')
 *
 * - query: (?)'param1=value1&param2=value2'
 * - query_array: array('param1'=>'value1', 'param2'=>'value2')
 * - fragment: (#)'fragment-anchor'
 *
 * URL structure:
 *
 * https://user:password@host:443/base_path/[base_script][scopeview_path]route_name/controller_name/action_name/param1/value1?query_param=query_value#fragment
 *       \__________A___________/\____________________________________B_____________________________________/
 * \__________________C___________________/              \__________________D_________________/ \_____E_____/
 * \_____________F______________/                        \___________________________G______________________/
 * \___________________________________________________H____________________________________________________/
 *
 * - A: authority
 * - B: path
 * - C: absolute_base_url
 * - D: action_path
 * - E: route_params
 * - F: host_url
 * - G: route_path
 * - H: route_url
 *
 * @category   Magento
 * @package    Magento_Core
 * @author     Magento Core Team <core@magentocommerce.com>
 */
namespace Magento;

class Url extends \Magento\Object implements \Magento\UrlInterface
{
    /**
     * Configuration data cache
     *
     * @var array
     */
    static protected $_configDataCache;

    /**
     * Encrypted session identifier
     *
     * @var string|null
     */
    static protected $_encryptedSessionId;

    /**
     * Reserved Route parameter keys
     *
     * @var array
     */
    protected $_reservedRouteParams = array(
        '_scope', '_type', '_secure', '_forced_secure', '_use_rewrite', '_nosid',
        '_absolute', '_current', '_direct', '_fragment', '_escape', '_query',
        '_scope_to_url'
    );

    /**
     * Request instance
     *
     * @var \Magento\App\RequestInterface
     */
    protected $_request;

    /**
     * Use Session ID for generate URL
     *
     * @var bool
     */
    protected $_useSession;

    /**
     * Url security info list
     *
     * @var \Magento\Url\SecurityInfoInterface
     */
    protected $_urlSecurityInfo;

    /**
     * @var \Magento\Core\Model\Session
     */
    protected $_session;

    /**
     * @var \Magento\Session\SidResolverInterface
     */
    protected $_sidResolver;

    /**
     * Constructor
     *
     * @var \Magento\App\Route\ConfigInterface
     */
    protected $_routeConfig;

    /**
     * @var \Magento\Url\RouteParamsResolverInterface
     */
    protected $_routeParamsResolver;

    /**
     * @var \Magento\Url\ScopeResolverInterface
     */
    protected $_scopeResolver;

    /**
     * @var \Magento\Url\QueryParamsResolverInterface
     */
    protected $_queryParamsResolver;

    /**
     * @param \Magento\App\Route\ConfigInterface $routeConfig
     * @param \Magento\App\RequestInterface $request
     * @param \Magento\Url\SecurityInfoInterface $urlSecurityInfo
     * @param \Magento\Url\ScopeResolverInterface $scopeResolver
     * @param \Magento\Session\Generic $session
     * @param \Magento\Session\SidResolverInterface $sidResolver
     * @param \Magento\Url\RouteParamsResolverFactory $routeParamsResolver
     * @param \Magento\Url\QueryParamsResolverInterface $queryParamsResolver
     * @param array $data
     */
    public function __construct(
        \Magento\App\Route\ConfigInterface $routeConfig,
        \Magento\App\RequestInterface $request,
        \Magento\Url\SecurityInfoInterface $urlSecurityInfo,
        \Magento\Url\ScopeResolverInterface $scopeResolver,
        \Magento\Session\Generic $session,
        \Magento\Session\SidResolverInterface $sidResolver,
        \Magento\Url\RouteParamsResolverFactory $routeParamsResolver,
        \Magento\Url\QueryParamsResolverInterface $queryParamsResolver,
        array $data = array()
    ) {
        $this->_request = $request;
        $this->_routeConfig = $routeConfig;
        $this->_urlSecurityInfo = $urlSecurityInfo;
        $this->_scopeResolver = $scopeResolver;
        $this->_session = $session;
        $this->_sidResolver = $sidResolver;
        $this->_routeParamsResolver = $routeParamsResolver->create();
        $this->_queryParamsResolver = $queryParamsResolver;
        parent::__construct($data);
    }

    /**
     * Get default url type
     *
     * @return string
     */
    protected function _getDefaultUrlType()
    {
        return \Magento\UrlInterface::URL_TYPE_LINK;
    }

    /**
     * Initialize object data from retrieved url
     *
     * @param   string $url
     * @return  \Magento\UrlInterface
     */
    protected  function _parseUrl($url)
    {
        $data = parse_url($url);
        $parts = array(
            'scheme'   => 'setScheme',
            'host'     => 'setHost',
            'port'     => 'setPort',
            'user'     => 'setUser',
            'pass'     => 'setPassword',
            'path'     => 'setPath',
            'query'    => 'setQuery',
            'fragment' => 'setFragment');

        foreach ($parts as $component => $method) {
            if (isset($data[$component])) {
                $this->$method($data[$component]);
            }
        }
        return $this;
    }

    /**
     * Retrieve default controller name
     *
     * @return string
     */
    protected function _getDefaultControllerName()
    {
        return self::DEFAULT_CONTROLLER_NAME;
    }

    /**
     * Set use session rule
     *
     * @param bool $useSession
     * @return \Magento\UrlInterface
     */
    public function setUseSession($useSession)
    {
        $this->_useSession = (bool) $useSession;
        return $this;
    }

    /**
     * Set route front name
     *
     * @param string $name
     * @return \Magento\UrlInterface
     */
    protected function _setRouteFrontName($name)
    {
        $this->setData('route_front_name', $name);
        return $this;
    }

    /**
     * Retrieve use session rule
     *
     * @return bool
     */
    public function getUseSession()
    {
        if (is_null($this->_useSession)) {
            $this->_useSession = $this->_sidResolver->getUseSessionInUrl();
        }
        return $this->_useSession;
    }

    /**
     * Retrieve default action name
     *
     * @return string
     */
    protected function _getDefaultActionName()
    {
        return self::DEFAULT_ACTION_NAME;
    }

    /**
     * Retrieve configuration data
     *
     * @param string $key
     * @param string|null $prefix
     * @return string
     */
    public function getConfigData($key, $prefix = null)
    {
        if (is_null($prefix)) {
            $prefix = 'web/' . ($this->_isSecure() ? 'secure' : 'unsecure').'/';
        }
        $path = $prefix . $key;

        $cacheId = $this->_getConfigCacheId($path);
        if (!isset(self::$_configDataCache[$cacheId])) {
            $data = $this->_getConfig($path);
            self::$_configDataCache[$cacheId] = $data;
        }

        return self::$_configDataCache[$cacheId];
    }

    /**
     * Get cache id for config path
     *
     * @param string $path
     * @return string
     */
    protected function _getConfigCacheId($path)
    {
        return $this->_getScope()->getCode() . '/' . $path;
    }

    /**
     * Get config data by path
     *
     * @param string $path
     * @return null|string
     */
    protected function _getConfig($path)
    {
        return $this->_getScope()->getConfig($path);
    }

    /**
     * Set request
     *
     * @param \Magento\App\RequestInterface $request
     * @return \Magento\UrlInterface
     */
    public function setRequest(\Magento\App\RequestInterface $request)
    {
        $this->_request = $request;
        return $this;
    }

    /**
     * Zend request object
     *
     * @return \Magento\App\RequestInterface
     */
    protected function _getRequest()
    {
        return $this->_request;
    }

    /**
     * Retrieve URL type
     *
     * @return string
     */
    protected function _getType()
    {
        if (!$this->_routeParamsResolver->hasData('type')) {
            $this->_routeParamsResolver->setData('type', $this->_getDefaultUrlType());
        }
        return $this->_routeParamsResolver->getType();
    }

    /**
     * @param string $type
     * @return mixed
     */
    public function setType($type)
    {
        $this->_routeParamsResolver->setType($type);
        return $this;
    }

    /**
     * Retrieve is secure mode URL
     *
     * @return bool
     */
    protected function _isSecure()
    {
        if ($this->_routeParamsResolver->hasData('secure_is_forced')) {
            return (bool)$this->_routeParamsResolver->getData('secure');
        }

        if (!$this->_getScope()->isUrlSecure()) {
            return false;
        }

        if (!$this->_routeParamsResolver->hasData('secure')) {
            if ($this->_getType() == \Magento\UrlInterface::URL_TYPE_LINK) {
                $pathSecure = $this->_urlSecurityInfo->isSecure('/' . $this->_getActionPath());
                $this->_routeParamsResolver->setData('secure', $pathSecure);
            } else {
                $this->_routeParamsResolver->setData('secure', true);
            }
        }

        return $this->_routeParamsResolver->getData('secure');
    }

    /**
     * Set scope entity
     *
     * @param mixed $params
     * @return \Magento\UrlInterface
     */
    public function setScope($params)
    {
        $this->setData('scope', $this->_scopeResolver->getScope($params));
        $this->_routeParamsResolver->setScope($this->_scopeResolver->getScope($params));
        return $this;
    }

    /**
     * Get current scope for the url instance
     *
     * @return \Magento\Url\ScopeInterface
     */
    protected function _getScope()
    {
        if (!$this->hasData('scope')) {
            $this->setScope(null);
        }
        return $this->_getData('scope');
    }

    /**
     * Retrieve Base URL
     *
     * @param array $params
     * @return string
     */
    public function getBaseUrl($params = array())
    {
        if (isset($params['_scope'])) {
            $this->setScope($params['_scope']);
        }
        if (isset($params['_type'])) {
            $this->_routeParamsResolver->setType($params['_type']);
        }

        if (isset($params['_secure'])) {
            $this->_routeParamsResolver->setSecure($params['_secure']);
        }

        /**
         * Add availability support urls without scope code
         */
        if ($this->_getType() == \Magento\UrlInterface::URL_TYPE_LINK
            && $this->_getRequest()->isDirectAccessFrontendName($this->_getRouteFrontName())) {
            $this->_routeParamsResolver->setType(\Magento\UrlInterface::URL_TYPE_DIRECT_LINK);
        }

        $result =  $this->_getScope()->getBaseUrl($this->_getType(), $this->_isSecure());
        $this->_routeParamsResolver->setType($this->_getDefaultUrlType());
        return $result;
    }

    /**
     * Set Route Parameters
     *
     * @param string $data
     * @return \Magento\UrlInterface
     */
    protected function _setRoutePath($data)
    {
        if ($this->_getData('route_path') == $data) {
            return $this;
        }

        $this->unsetData('route_path');
        $routePieces = explode('/', $data);

        $route = array_shift($routePieces);
        if ('*' === $route) {
            $route = $this->_getRequest()->getRequestedRouteName();
        }
        $this->_setRouteName($route);

        $controller = '';
        if (!empty($routePieces)) {
            $controller = array_shift($routePieces);
            if ('*' === $controller) {
                $controller = $this->_getRequest()->getRequestedControllerName();
            }
        }
        $this->_setControllerName($controller);

        $action = '';
        if (!empty($routePieces)) {
            $action = array_shift($routePieces);
            if ('*' === $action) {
                $action = $this->_getRequest()->getRequestedActionName();
            }
        }
        $this->_setActionName($action);

        if (!empty($routePieces)) {
            while (!empty($routePieces)) {
                $key = array_shift($routePieces);
                if (!empty($routePieces)) {
                    $value = array_shift($routePieces);
                    $this->_routeParamsResolver->setRouteParam($key, $value);
                }
            }
        }

        return $this;
    }

    /**
     * Retrieve action path
     *
     * @return string
     */
    protected function _getActionPath()
    {
        if (!$this->_getRouteName()) {
            return '';
        }

        $hasParams = (bool) $this->_getRouteParams();
        $path = $this->_getRouteFrontName() . '/';

        if ($this->_getControllerName()) {
            $path .= $this->_getControllerName() . '/';
        } elseif ($hasParams) {
            $path .= $this->_getDefaultControllerName() . '/';
        }
        if ($this->_getActionName()) {
            $path .= $this->_getActionName() . '/';
        } elseif ($hasParams) {
            $path .= $this->_getDefaultActionName() . '/';
        }

        return $path;
    }

    /**
     * Retrieve route path
     *
     * @param array $routeParams
     * @return string
     */
    protected function _getRoutePath($routeParams = array())
    {
        if (!$this->hasData('route_path')) {
            $routePath = $this->_getRequest()->getAlias(self::REWRITE_REQUEST_PATH_ALIAS);
            if (!empty($routeParams['_use_rewrite']) && ($routePath !== null)) {
                $this->setData('route_path', $routePath);
                return $routePath;
            }
            $routePath = $this->_getActionPath();
            if ($this->_getRouteParams()) {
                foreach ($this->_getRouteParams() as $key=>$value) {
                    if (is_null($value) || false === $value || '' === $value || !is_scalar($value)) {
                        continue;
                    }
                    $routePath .= $key . '/' . $value . '/';
                }
            }
            if ($routePath != '' && substr($routePath, -1, 1) !== '/') {
                $routePath .= '/';
            }
            $this->setData('route_path', $routePath);
        }
        return $this->_getData('route_path');
    }

    /**
     * Set route name
     *
     * @param string $data
     * @return \Magento\UrlInterface
     */
    protected function _setRouteName($data)
    {
        if ($this->_getData('route_name') == $data) {
            return $this;
        }
        $this->unsetData('route_front_name')
            ->unsetData('route_path')
            ->unsetData('controller_name')
            ->unsetData('action_name')
            ->unsetData('secure');
        return $this->setData('route_name', $data);
    }

    /**
     * Retrieve route front name
     *
     * @return string
     */
    protected function _getRouteFrontName()
    {
        if (!$this->hasData('route_front_name')) {
            $frontName = $this->_routeConfig->getRouteFrontName(
                $this->_getRouteName(),
                $this->_scopeResolver->getAreaCode()
            );
            $this->_setRouteFrontName($frontName);
        }

        return $this->_getData('route_front_name');
    }

    /**
     * Retrieve route name
     *
     * @param mixed $default
     * @return string|null
     */
    protected function _getRouteName($default = null)
    {
        return $this->_getData('route_name') ? $this->_getData('route_name') : $default;
    }

    /**
     * Set Controller Name
     *
     * Reset action name and route path if has change
     *
     * @param string $data
     * @return \Magento\UrlInterface
     */
    protected function _setControllerName($data)
    {
        if ($this->_getData('controller_name') == $data) {
            return $this;
        }
        $this->unsetData('route_path')->unsetData('action_name')->unsetData('secure');
        return $this->setData('controller_name', $data);
    }

    /**
     * Retrieve controller name
     *
     * @return string|null
     */
    protected function _getControllerName()
    {
        return $this->_getData('controller_name') ? $this->_getData('controller_name') : null;
    }

    /**
     * Set Action name
     * Reseted route path if action name has change
     *
     * @param string $data
     * @return \Magento\UrlInterface
     */
    protected function _setActionName($data)
    {
        if ($this->_getData('action_name') == $data) {
            return $this;
        }
        $this->unsetData('route_path');
        return $this->setData('action_name', $data)->unsetData('secure');
    }

    /**
     * Retrieve action name
     *
     * @param mixed $default
     * @return string|null
     */
    protected function _getActionName($default = null)
    {
        return $this->_getData('action_name') ? $this->_getData('action_name') : $default;
    }

    /**
     * Set route params
     *
     * @param array $data
     * @param boolean $unsetOldParams
     * @return \Magento\UrlInterface
     */
    protected function _setRouteParams(array $data, $unsetOldParams = true)
    {
        $this->_routeParamsResolver->setRouteParams($data, $unsetOldParams);
        return $this;
    }

    /**
     * Retrieve route params
     *
     * @return array
     */
    protected function _getRouteParams()
    {
        return $this->_routeParamsResolver->getRouteParams();
    }

    /**
     * Retrieve route URL
     *
     * @param string $routePath
     * @param array $routeParams
     * @return string
     */
    public function getRouteUrl($routePath = null, $routeParams = null)
    {
        if (filter_var($routePath, FILTER_VALIDATE_URL)) {
            return $routePath;
        }

        $this->_routeParamsResolver->unsetData('route_params');

        if (isset($routeParams['_direct'])) {
            if (is_array($routeParams)) {
                $this->_setRouteParams($routeParams, false);
            }
            return $this->getBaseUrl() . $routeParams['_direct'];
        }

        $this->_setRoutePath($routePath);
        if (is_array($routeParams)) {
            $this->_setRouteParams($routeParams, false);
        }

        return $this->getBaseUrl() . $this->_getRoutePath($routeParams);
    }

    /**
     * Add session param
     *
     * @return \Magento\UrlInterface
     */
    public function addSessionParam()
    {
        if (!self::$_encryptedSessionId) {
            self::$_encryptedSessionId = $this->_session->getSessionId();
        }
        $this->setQueryParam($this->_sidResolver->getSessionIdQueryParam($this->_session), self::$_encryptedSessionId);
        return $this;
    }

    /**
     * Set URL query param(s)
     *
     * @param mixed $data
     * @return \Magento\UrlInterface
     */
    protected function _setQuery($data)
    {
        return $this->_queryParamsResolver->setQuery($data);
    }

    /**
     * Get query params part of url
     *
     * @param bool $escape "&" escape flag
     * @return string
     */
    protected function _getQuery($escape = false)
    {
        return $this->_queryParamsResolver->getQuery($escape);
    }

    /**
     * Set query Params as array
     *
     * @param array $data
     * @return \Magento\UrlInterface
     */
    public function setQueryParams(array $data)
    {
        $this->_queryParamsResolver->setQueryParams($data);
        return $this;
    }

    /**
     * Purge Query params array
     *
     * @return \Magento\UrlInterface
     */
    public function purgeQueryParams()
    {
        return $this->_queryParamsResolver->purgeQueryParams();
    }

    /**
     * Return Query Params
     *
     * @return array
     */
    protected function _getQueryParams()
    {
        return $this->_queryParamsResolver->getQueryParams();
    }

    /**
     * Set query param
     *
     * @param string $key
     * @param mixed $data
     * @return \Magento\UrlInterface
     */
    public function setQueryParam($key, $data)
    {
        return $this->_queryParamsResolver->setQueryParam($key, $data);
    }

    /**
     * Retrieve URL fragment
     *
     * @return string|null
     */
    protected function _getFragment()
    {
        return $this->_getData('fragment');
    }

    /**
     * Build url by requested path and parameters
     *
     * @param   string|null $routePath
     * @param   array|null $routeParams
     * @return  string
     */
    public function getUrl($routePath = null, $routeParams = null)
    {
        if (filter_var($routePath, FILTER_VALIDATE_URL)) {
            return $routePath;
        }

        $escapeQuery = false;

        /**
         * All system params should be unset before we call getRouteUrl
         * this method has condition for adding default controller and action names
         * in case when we have params
         */
        $fragment = null;
        if (isset($routeParams['_fragment'])) {
            $fragment = $routeParams['_fragment'];
            unset($routeParams['_fragment']);
        }

        if (isset($routeParams['_escape'])) {
            $escapeQuery = $routeParams['_escape'];
            unset($routeParams['_escape']);
        }

        $query = null;
        if (isset($routeParams['_query'])) {
            $this->purgeQueryParams();
            $query = $routeParams['_query'];
            unset($routeParams['_query']);
        }

        $noSid = null;
        if (isset($routeParams['_nosid'])) {
            $noSid = (bool)$routeParams['_nosid'];
            unset($routeParams['_nosid']);
        }
        $url = $this->getRouteUrl($routePath, $routeParams);
        /**
         * Apply query params, need call after getRouteUrl for rewrite _current values
         */
        if ($query !== null) {
            if (is_string($query)) {
                $this->_setQuery($query);
            } elseif (is_array($query)) {
                $this->setQueryParams($query, !empty($routeParams['_current']));
            }
            if ($query === false) {
                $this->setQueryParams(array());
            }
        }

        if ($noSid !== true) {
            $this->_prepareSessionUrl($url);
        }

        $query = $this->_getQuery($escapeQuery);
        if ($query) {
            $mark = (strpos($url, '?') === false) ? '?' : ($escapeQuery ? '&amp;' : '&');
            $url .= $mark . $query;
            $this->_queryParamsResolver->unsetData('query');
            $this->_queryParamsResolver->unsetData('query_params');
        }

        if (!is_null($fragment)) {
            $url .= '#' . $fragment;
        }

        return $this->escape($url);
    }

    /**
     * Check and add session id to URL
     *
     * @param string $url
     *
     * @return \Magento\UrlInterface
     */
    protected function _prepareSessionUrl($url)
    {
        return $this->_prepareSessionUrlWithParams($url, array());
    }

    /**
     * Check and add session id to URL, session is obtained with parameters
     *
     * @param string $url
     * @param array $params
     *
     * @return \Magento\UrlInterface
     */
    protected function _prepareSessionUrlWithParams($url, array $params)
    {
        if (!$this->getUseSession()) {
            return $this;
        }
        $sessionId = $this->_session->getSessionIdForHost($url);
        if ($this->_sidResolver->getUseSessionVar() && !$sessionId) {
            $this->setQueryParam('___SID', $this->_isSecure() ? 'S' : 'U'); // Secure/Unsecure
        } else if ($sessionId) {
            $this->setQueryParam($this->_sidResolver->getSessionIdQueryParam($this->_session), $sessionId);
        }
        return $this;
    }

    /**
     * Rebuild URL to handle the case when session ID was changed
     *
     * @param string $url
     * @return string
     */
    public function getRebuiltUrl($url)
    {
        $this->_parseUrl($url);
        $port = $this->getPort();
        if ($port) {
            $port = ':' . $port;
        } else {
            $port = '';
        }
        $url = $this->getScheme() . '://' . $this->getHost() . $port . $this->getPath();

        $this->_prepareSessionUrl($url);

        $query = $this->_getQuery();
        if ($query) {
            $url .= '?' . $query;
        }

        $fragment = $this->_getFragment();
        if ($fragment) {
            $url .= '#' . $fragment;
        }

        return $this->escape($url);
    }

    /**
     * Escape (enclosure) URL string
     *
     * @param string $value
     * @return string
     */
    public function escape($value)
    {
        $value = str_replace('"', '%22', $value);
        $value = str_replace("'", '%27', $value);
        $value = str_replace('>', '%3E', $value);
        $value = str_replace('<', '%3C', $value);
        return $value;
    }

    /**
     * Build url by direct url and parameters
     *
     * @param string $url
     * @param array $params
     * @return string
     */
    public function getDirectUrl($url, $params = array())
    {
        $params['_direct'] = $url;
        return $this->getUrl('', $params);
    }

    /**
     * Replace Session ID value in URL
     *
     * @param string $html
     * @return string
     */
    public function sessionUrlVar($html)
    {
        return preg_replace_callback('#(\?|&amp;|&)___SID=([SU])(&amp;|&)?#',
            array($this, "_sessionVarCallback"), $html);
    }

    /**
     * Check and return use SID for URL
     *
     * @param bool $secure
     * @return bool
     */
    public function useSessionIdForUrl($secure = false)
    {
        $key = 'use_session_id_for_url_' . (int) $secure;
        if (is_null($this->getData($key))) {
            $httpHost = $this->_request->getHttpHost();
            $urlHost = parse_url($this->_getScope()->getBaseUrl(\Magento\UrlInterface::URL_TYPE_LINK, $secure),
                PHP_URL_HOST);

            if ($httpHost != $urlHost) {
                $this->setData($key, true);
            } else {
                $this->setData($key, false);
            }
        }
        return $this->getData($key);
    }

    /**
     * Callback function for session replace
     *
     * @param array $match
     * @return string
     */
    protected function _sessionVarCallback($match)
    {
        if ($this->useSessionIdForUrl($match[2] == 'S' ? true : false)) {
            return $match[1]
                . $this->_sidResolver->getSessionIdQueryParam($this->_session)
                . '=' . $this->_session->getSessionId()
                . (isset($match[3]) ? $match[3] : '');
        } else {
            if ($match[1] == '?' && isset($match[3])) {
                return '?';
            } elseif ($match[1] == '?' && !isset($match[3])) {
                return '';
            } elseif (($match[1] == '&amp;' || $match[1] == '&') && !isset($match[3])) {
                return '';
            } elseif (($match[1] == '&amp;' || $match[1] == '&') && isset($match[3])) {
                return $match[3];
            }
        }
        return '';
    }

    /**
     * Check if users originated URL is one of the domain URLs assigned to scopes
     *
     * @return boolean
     */
    public function isOwnOriginUrl()
    {
        $scopeDomains = array();
        $referer = parse_url($this->_request->getServer('HTTP_REFERER'), PHP_URL_HOST);
        foreach ($this->_scopeResolver->getScopes() as $scope) {
            $scopeDomains[] = parse_url($scope->getBaseUrl(), PHP_URL_HOST);
            $scopeDomains[] = parse_url($scope->getBaseUrl(
                \Magento\UrlInterface::URL_TYPE_LINK, true), PHP_URL_HOST
            );
        }
        $scopeDomains = array_unique($scopeDomains);
        if (empty($referer) || in_array($referer, $scopeDomains)) {
            return true;
        }
        return false;
    }

    /**
     * Return frontend redirect URL with SID and other session parameters if any
     *
     * @param string $url
     *
     * @return string
     */
    public function getRedirectUrl($url)
    {
        $this->_prepareSessionUrlWithParams($url, array(
            'name' => self::SESSION_NAMESPACE
        ));

        $query = $this->_getQuery(false);
        if ($query) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . $query;
        }

        return $url;
    }

    /**
     * Retrieve current url
     *
     * @return string
     */
    public function getCurrentUrl()
    {
        $port = $this->_request->getServer('SERVER_PORT');
        if ($port) {
            $defaultPorts = array(
                \Magento\App\Request\Http::DEFAULT_HTTP_PORT,
                \Magento\App\Request\Http::DEFAULT_HTTPS_PORT
            );
            $port = (in_array($port, $defaultPorts)) ? '' : ':' . $port;
        }
        $requestUri = $this->_request->getServer('REQUEST_URI');
        $url = $this->_request->getScheme() . '://' . $this->_request->getHttpHost()
            . $port . $requestUri;
        return $url;
    }
}
