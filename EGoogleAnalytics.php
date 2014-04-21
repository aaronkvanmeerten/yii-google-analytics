<?php

/**
 * Google Analytics code generation widget
 *
 * @copyright © Digitick <www.digitick.net> 2012
 * @license GNU Lesser General Public License v3.0
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Lesser Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 * 
 */

/**
 * Google Analytics widget class.
 *
 * @author Ianaré Sévi
 */
class EGoogleAnalytics extends CApplicationComponent
{
	/**
	 * @var string The full web property ID (e.g. UA-65432-1) for the tracker object.
	 */
	public $account;

	/**
	 * @var boolean linker functionality flag as part of enabling cross-domain
	 * user tracking. 
	 */
	public $allowLinker = true;
	
	 // Домены, на которые ведет эта страница.
	public $linkerDestination = '';

	/**
	 * @var string the new cookie path for your site. By default, Google Analytics
	 * sets the cookie path to the root level (/).
	 */
	public $cookiePath;

	/**
	 * @var string the domain name for the GATC cookies. Values: 'auto', 'none', [domain].
	 * [domain] is a specific domain, i.e. 'example.com'.
	 * 'auto' by default.
	 */
	public $domainName = 'auto';

	/**
	 * @var array strings of ignored term(s) for Keywords reports.
	 * 
	 * array('keyword1', 'keyword2')
	 */
	public $ignoredOrganics = array();

	/**
	 * @var array Excludes a source as a referring site.
	 * 
	 * array('referrer1', 'referrer2')
	 */
	public $ignoredRefs = array();

	/**
	 * @var array Adds a search engine to be included as a potential search
	 * engine traffic source.
	 * 
	 * array('engine1' => 'keyword2', 'engine2' => 'keyword2')
	 */
	public $organics = array();

	/**
	 * @var boolean the browser tracking module flag.
	 */
	public $clientInfo = true;

	/**
	 *
	 * @var boolean the Flash detection flag.
	 */
	public $detectFlash = true;

	/**
	 *
	 * @var boolean the title detection flag.
	 */
	public $detectTitle = true;
	
	
	/**
	 *
	 * @var boolean use newest version of ga, see https://developers.google.com/analytics/devguides/collection/upgrade/reference/gajs-analyticsjs.
	 */
	public $useAnalyticsJs = true;

	/**
	 * @var array items purchased, multidimensional.
	 * 
	 * array(
	 * 		array(
	 * 			'orderId' => '1234',
	 * 			'sku' => 'DD44',
	 * 			'name' => 'T-Shirt',
	 * 			'category' => 'Olive Medium',
	 * 			'price' => '11.99',
	 * 			'quantity' => '1'
	 * 		),
	 * )
	 */
	public $items = array();

	/**
	 * @var array commercial transactions, multidimensional.
	 * 
	 * array(
	 * 		array(
	 * 			'orderId' => '1234',
	 * 			'affiliation' => 'Womens Apparel',
	 * 			'total' => '28.28',
	 * 			'tax' => '1.29',
	 * 			'shipping' => '15.00',
	 * 			'city' => 'San Jose',
	 * 			'state' => 'California',
	 * 			'country' => 'USA'
	 * 		),
	 * )
	 */
	public $transactions = array();
	
	/**
	 * @var array events, multidimensional.
 	 *
	 * array(
	 * 		array(
	 * 			'category' => 'Videos',
	 * 			'action' => 'Play',
	 * 			'label' => 'Gone With the Wind',
	 * 			'value' => '60',
	 * 			'interaction' => 'true',
	 * 		),
	 * )
 	 */
 	public $events = array();
 
 	/**
	

	/**
	 * @var string CClientScript position. 
	 */
	public $position = CClientScript::POS_HEAD;

	
	private $_script;
	
	/**
	 * initialization
	 */
	public function run()
	{
		if (!$this->account)
			throw new CException('Google analytics account ID must be set.');

		if (!$this->useAnalyticsJs)
		{
			$this->_generateGaJsSnippet();
		} else {
			$this->_generateAnalyticsJsSnippet();
		}
		Yii::app()->getClientScript()->registerScript('google-analytics', $this->_script, $this->position);
		
		$trackTrans = $ecommerce = '';
		$transactions = $this->_addTransactions();
		$items = $this->_addItems();
		 // Отправка сведений о транзакции и товаре в Google Analytics.
		if (!empty($items) || !empty($transactions))
		{
			if ($this->useAnalyticsJs) {
				$ecommerce = "ga('require', 'ecommerce', 'ecommerce.js');\n"; // Загрузка плагина электронной торговли.
			} 
			$trackTrans = $this->useAnalyticsJs ? "ga('ecommerce:send');\n" : "_gaq.push(['_trackTrans']);\n";
		}	
		$events = $this->_addEvents();
		$supplementScript = $ecommerce . $transactions . $items . $trackTrans . $events;
		$supplementId = 'google-analytics-supplement-' . md5($supplementScript);
		Yii::app()->getClientScript()->registerScript($supplementId, $supplementScript, $this->position);
	}
	
	/**
	 * ga.js snippet
	 */
	private function _generateGaJsSnippet()
	{
		$script = "var _gaq = _gaq || [];\n";
		$script .= $this->_setAccount();
		$script .= $this->_setDomainName();
		$script .= $this->_setOrganics();
		$script .= $this->_setCookiePath();
		$script .= $this->_setAllowLinker();
		
		if (!$this->clientInfo)
			$script .= "_gaq.push(['_setClientInfo', false]);\n";
		if (!$this->detectFlash)
			$script .= "_gaq.push(['_setDetectFlash', false]);\n";
		if (!$this->detectTitle)
			$script .= "_gaq.push(['_setDetectTitle', false]);\n";
		
		$script .= $this->_trackPageview();
		$script .= $this->_baseScript();
		$this->_script = $script;
	}
	
	/**
	 * analytics.js snippet
	 */
	private function _generateAnalyticsJsSnippet()
	{
		$script = $this->_baseScript();
		$script .= $this->_setAccount();
		$script .= $this->_trackPageview();
		$script .= $this->_setLinkerDestination();
		$this->_script = $script;
	}
	
	/**
	 * base script for Both variants
	 */
	private function _baseScript()
	{
		return $this->useAnalyticsJs ? 
			"(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){ (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o), m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m) })(window,document,'script','//www.google-analytics.com/analytics.js','ga');\n"
			:
			"(function() {
                var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
                ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
                var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
            })();";
	}
	
	/**
	 * Setting ga account
	 */
	private function _setAccount()
	{
		return $this->useAnalyticsJs ? 
			"ga('create', '{$this->account}'" . 
				$this->_setDomainName() .
				$this->_setAnalyticsJsCreateString() . 
			");\n"
			: 
			"_gaq.push(['_setAccount', '{$this->account}']);\n";
	}
	
	/**
	 * Setting domain name
	 */
	private function _setDomainName()
	{
		return $this->useAnalyticsJs ? ", '{$this->domainName}'" : "_gaq.push(['_setDomainName', '{$this->domainName}']);\n";
	}
	
	/**
	 * Adding Events, as falk made here https://github.com/falk/yii-google-analytics/commit/dec19fb470366542bdb047e269d56e1cddf04333
	 */
	private function _addEvents()
	{
		$events = '';
 		foreach ($this->events as $event) {
 			$event['label'] = (isset($event['label'])) ? $event['label'] : false;
 			$event['value'] = (isset($event['value'])) ? $event['value'] : false;
 			$event['interaction'] = (isset($event['interaction'])) ? $event['interaction'] : false;
			
			if ($this->useAnalyticsJs) {
				$events .= "ga('send', 'event', '{$event['category']}', '{$event['action']}'";
				if ($event['label'] !== false)
					$events .= ", '{$event['label']}'";
				if ($event['value'] !== false)
					$events .= ", {$event['value']}";
				if ($event['interaction'] !== false)
					$events .= ", \{'nonInteraction': {$event['interaction']}\}";
				$events .= ");\n";
			} else {
				$events .= "_gaq.push(['_trackEvent', '{$event['category']}', '{$event['action']}'";
				if ($event['label'])
					$events .= ", '{$event['label']}'";
				if ($event['value'])
					$events .= ", {$event['value']}";
				if ($event['interaction'])
					$events .= ", {$event['interaction']}";
	 
				$events .= "]);\n";
			}
 		}
		return $events;
	}
	
	/**
	 * id Идентификатор транзакции (обязательно). 
	 * name Наименование товара (обязательно).
	 * sku Код единицы складского учета.
	 * category  // Категория или вид изделия.
	 * price // Цена за единицу товара. 
	 * quantity  // Количество.
	 */
	private function _addItems()
	{
		$items = '';
		// Метод addItem должен вызываться для каждого товара в корзине покупок. cart. 
		foreach ($this->items as $item) {
			if ($this->useAnalyticsJs) {
				"ga( 'ecommerce:addItem', { 'id': '{$item['orderId']}', 'name': '{$item['name']}',  'sku': '{$item['sku']}', 'category': '{$item['category']}', 'price': '{$item['price']}', 'quantity': '{$item['quantity']}' });\n";
			} else {
				$items .= "_gaq.push(['_addItem', '{$item['orderId']}', '{$item['sku']}', '{$item['name']}', '{$item['category']}', '{$item['price']}', '{$item['quantity']}']);\n";
			}
		}
		return $items;
	}
	
	/**
	 * id // Transaction ID. Required  
	 * affiliation // Affiliation or store name 
	 * revenue // Общая сумма транзакции. 
	 * shipping  // Доставка.
	 * tax  // Налог.
	 */
	private function _addTransactions()
	{
		$transactions = '';
		foreach ($this->transactions as $trans) {
			if ($this->useAnalyticsJs) {
				$transactions .= "ga('ecommerce:addTransaction', { 'id': '{$trans['orderId']}', 'affiliation': '{$trans['affiliation']}', 'revenue': '{$trans['total']}', 'shipping': '{$trans['shipping']}',  'tax': '{$trans['tax']}' });\n";
			} else {
				$transactions .= "_gaq.push(['_addTrans', '{$trans['orderId']}', '{$trans['affiliation']}', '{$trans['total']}', '{$trans['tax']}', '{$trans['shipping']}', '{$trans['city']}', '{$trans['state']}', '{$trans['country']}']);\n";
			}
		}
		return $transactions;
	}
	
	/**
	 * TrackPageView
	 */
	private function _trackPageview (){
		return $this->useAnalyticsJs ? "ga('send', 'pageview');\n" : "_gaq.push(['_trackPageview']);\n";
	}

	/**
	 * Setting Organics , Refs and Ignore Organics
	 * Only for ga.js
	 */
	private function _setOrganics()
	{
		$ignoredOrganics = '';
		foreach ($this->ignoredOrganics as $keyword) {
			$ignoredOrganics .= "_gaq.push(['_addIgnoredOrganic', '$keyword']);\n";
		}
		$ignoredRefs = '';
		foreach ($this->ignoredRefs as $referrer) {
			$ignoredRefs .= "_gaq.push(['_addIgnoredRef', '$referrer']);\n";
		}
		$organics = '';
		foreach ($this->organics as $engine => $keyword) {
			$organics .= "_gaq.push(['_addOrganic','$engine', '$keyword']);\n";
		}
		return $ignoredOrganics . $ignoredRefs . $organics;
	}
	
	/**
	 * Setting cookie path
	 */
	private function _setCookiePath(){
		if ( $this->cookiePath === null )
			return '';
		return $this->useAnalyticsJs ? "'cookiePath': '{$this->cookiePath}'" : "_gaq.push(['_setCookiePath', '{$this->cookiePath}']);\n";
	}
	
	/**
	 * creating of analytics.js options object
	 */
	private function _setAnalyticsJsCreateString()
	{
		$array = array();
		$this->_addNotEmpty($array,$this->_setCookiePath());
		$this->_addNotEmpty($array,$this->_setAllowLinker());
		return ",{" . implode(",",$array) . "}";
	}
	
	/**
	 * Setting alow Linker
	 */
	private function _setAllowLinker() 
	{
		$bool = (($this->allowLinker) ? 'true' : 'false');
		return $this->useAnalyticsJs ?  "'allowLinker': $bool" : "_gaq.push(['_setAllowLinker', $bool]);\n";
	}
	
	/**
	 * Setting destination
	 */
	private function _setLinkerDestination()
	{
		return $this->allowLinker ? "ga('require', 'linker'); \n ga('linker:autoLink', ['{$this->linkerDestination}']);" : ''; 
	}
	
	
	private function _addNotEmpty(&$array, $value)
	{
		if ( trim($value) != '' )
		{
			$array[] = $value;
		}
	}
	
}
