<?php
/*********************************
	Laravel 4 Z-Ray Extension
	Version: 1.00
**********************************/
namespace ZRay;

class Laravel {
	
	private $visibleConfigurations=array(); //Put here Laravel configurations files which you want to be display (eg. app, database, cache, full list on /app/config/) 
	private $tracedAlready = false;
	private $zre = null;
	
	public function setZRE($zre) {
		$this->zre = $zre;
	}

	public function laravelRunExit($context,&$storage){
		global $app,$zend_laravel_views,$zend_laravel_events;
		if (version_compare($app::VERSION,4,'<')||version_compare($app::VERSION,5,'>=')) {
			return; //this extension support only laravel 4
		}
		$this->loadLaravelPanel($storage);
		if (version_compare($app::VERSION,4.1,'>=')) {
			$this->loadLaravelRoutePanel($storage);
			$this->loadSessionPanel($storage);
			$this->loadUserPanel($storage);
		}else{
			if (is_a(\Route::getCurrentRoute(), 'Symfony\Component\Routing\Route')){
				$this->loadSymfonyRoutePanel($storage);
			}
		}		
		
		$this->loadConfigurationPanel($storage);

		$storage['views']=$zend_laravel_views;
		$storage['eventsLog']=$zend_laravel_events;
	}
	public function laravelBeforeRun($context,&$storage){
		global $app;
		if (version_compare($app::VERSION,4,'<')||version_compare($app::VERSION,5,'>=')) {
			return; //this extension support only laravel 4
		}
		if (version_compare($app::VERSION,4.1,'<')) {
			$this->loadSessionPanel($storage);
			$this->loadUserPanel($storage);
		}
		$this->loadViewPanel($storage);
		$this->loadEventsPanel($storage);
	}
	public function loadConfigurationPanel(&$storage){
		foreach($this->visibleConfigurations as $conf){
			$storage['Configurations'][$conf]=\Config::get($conf);
		}
	}
	public function loadSessionPanel(&$storage){
		global $app;
		$data = array();
		foreach ($app['session']->all() as $key => $value) {
			$storage['session'][$key] = array('Name'=>$key,'Value'=>$value);
        }
		return $data;
	}
	public function loadEventsPanel(&$storage){
		global $app,$zend_laravel_events;
		$zend_laravel_events=array();
		$events=$app['events'];
		$events->listen(
			'*',
			function () use ( $events) {
				global $zend_laravel_events;
				if (method_exists($events, 'firing')) {
					$event = $events->firing();
				} else {
					$args = func_get_args();
					$event = end($args);
				}
				$zend_laravel_events[]=array('Name'=>$event);
			}
		);
	}
	public function loadViewPanel(&$storage){
		if($this->tracedAlready){
			return;
		}else{
			$this->tracedAlready=true;
		}
		global $app,$zend_laravel_views;
		$zend_laravel_views=array();
		$app['events']->listen(
                    'composing:*',
                    function ($view) use (&$storage) {
						global $zend_laravel_views;
						$data=array();
						foreach ($view->getData() as $key => $value) {
							if (is_object($value) && method_exists($value, 'toArray')) {
								$value = $value->toArray();
							}
							$data[$key] = $this->exportValue($value);
						}
						$zend_laravel_views[$view->getName()] = array(
							'Path'=>$view->getPath(),
							'Params ('.count($data).')'=>$data,
						);
                    }
                );
		
	}
	protected function loadLaravelPanel(&$storage){
		global $app;
		$storage['laravel'][] = array('Name'=>'Application Path','Value'=>app_path());
		$storage['laravel'][] = array('Name'=>'Base Path','Value'=>base_path());
		$storage['laravel'][] = array('Name'=>'Public Path','Value'=>public_path());
		$storage['laravel'][] = array('Name'=>'Storage Path','Value'=>storage_path());
		$storage['laravel'][] = array('Name'=>'URL Path','Value'=>url());
		$storage['laravel'][] = array('Name'=>'Environment','Value'=>\App::environment());
		$storage['laravel'][] = array('Name'=>'Version','Value'=>$app::VERSION);
		$storage['laravel'][] = array('Name'=>'Locale','Value'=>$app->getLocale());
	}
	protected function loadUserPanel(&$storage){
		global $app;
		$user = $app['auth']->user();
		if(!$user){
			//guest
			$storage['userInformation'][]=array('Name'=>'Guest','Additional Info'=>'Not Logged-in');
			return;
		}
		
		$storage['userInformation'][]=$user->toArray();
	}
	protected function loadSymfonyRoutePanel(&$storage){
		$name = \Route::currentRouteName();
		$route = \Route::getCurrentRoute();
		$routePanel = array();
		if(!empty($route->getHost())){
			$routePanel['Host']=$route->getHost();
		}
		if(!empty($name)){
			$routePanel['Name']=$name;
		}
		if(!empty($route->getPath())){
			$routePanel['Path']=$route->getPath();
		}
		$routePanel['Action']=$route->getAction() ?:'Closure';
		$routePanel['Before Filters']=$route->getBeforeFilters();
		$routePanel['After Filters']=$route->getAfterFilters();
		
		$storage['route'][]=$routePanel;
	}
	protected function loadLaravelRoutePanel(&$storage){
		$route = \Route::getCurrentRoute();
		$routePanel = array();
		if(!empty($route->domain())){
			$routePanel['Host']=$route->domain();
		}
		if(!empty($route->getName())){
			$routePanel['Name']=$route->getName();
		}
		if(!empty($route->getPath())){
			$routePanel['Path']=$route->getPath();
		}
		$routePanel['Action']=$route->getActionName();
		$routePanel['Before Filters']=$route->beforeFilters();
		$routePanel['After Filters']=$route->afterFilters();
		
		$storage['route'][]=$routePanel;
	}
	protected function exportValue($value, $depth = 1, $deep = false)
    {
        if (is_object($value)) {
            return sprintf('Object(%s)', get_class($value));
        }

        if (is_array($value)) {
            if (empty($value)) {
                return '[]';
            }

            $indent = str_repeat('  ', $depth);

            $a = array();
            foreach ($value as $k => $v) {
                if (is_array($v)) {
                    $deep = true;
                }
                $a[] = sprintf('%s => %s', $k, $this->exportValue($v, $depth + 1, $deep));
            }

            if ($deep) {
                return sprintf("[\n%s%s\n%s]", $indent, implode(sprintf(", \n%s", $indent), $a), str_repeat('  ', $depth - 1));
            }

            return sprintf("[%s]", implode(', ', $a));
        }

        if (is_resource($value)) {
            return sprintf('Resource(%s#%d)', get_resource_type($value), $value);
        }

        if (null === $value) {
            return 'null';
        }

        if (false === $value) {
            return 'false';
        }

        if (true === $value) {
            return 'true';
        }

        return (string) $value;
    }
}

$zre = new \ZRayExtension("laravel");

$zrayLaravel = new Laravel();
$zrayLaravel->setZRE($zre);

$zre->setMetadata(array(
	'logo' => __DIR__ . DIRECTORY_SEPARATOR . 'logo.png',
));

$zre->setEnabledAfter('Illuminate\Foundation\Application::detectEnvironment');
$zre->traceFunction('Illuminate\Foundation\Application::boot',function(){},array($zrayLaravel,'laravelBeforeRun'));
$zre->traceFunction('Illuminate\Foundation\Application::shutdown',function(){},array($zrayLaravel,'laravelRunExit'));