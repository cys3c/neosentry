/* Additional custom functions for NeSentry NMS */

/* Flatiron director routing
var router = Router({
    '': function(){ loadPage("#dashboard", "#app-content", "dashboard.html");},
    '/dashboard': function(){ loadPage("#dashboard", "#app-content", "dashboard.html");},
    '/settings': function(){ loadPage("#settings", "#app-content", "settings.html");},
    '/logs': function(){ loadPage("#logs", "#app-content", "logs.html");},
    '/devices': {
        '/:id': { on: function(id){ loadPage("#devices", "#app-content", "devices.php?name=" + id ); }},
        on: function(){ loadPage("#devices", "#app-content", "devices.php");}
    }
}).configure();*/
/*var allCalls = function(){console.log("do something every call");};
 router.configure({on: allCalls});*/

/*
$( document ).ready(function() {
    if(!window.location.hash) window.location.hash = "#/dashboard"; // set the default location
    router.init();
}); */
/* End Routing */


var app = angular.module("neosentry", ["ngRoute"]);
app.config(function($routeProvider, $locationProvider) {
    $routeProvider
        .when("/", {
            templateUrl : "dashboard.html",
            controller : "dashboardCtrl"
        })
        .when("/dashboard", {
            templateUrl : "dashboard.html",
            controller : "dashboardCtrl",
            activeTab: 'dashboard'
        })
        .when("/devices", {
            templateUrl : "device-list.html",
            controller : "devicesCtrl",
            activeTab: 'devices'
        })
        .when("/devices/:deviceId", {
            templateUrl : "device-details.html",
            controller : "devicesDetailCtrl",
            activeTab: 'devices'
        })
        .when("/logs", {
            templateUrl : "logs.html",
            controller : "logsCtrl",
            activeTab: 'logs'
        })
        .when("/settings", {
            templateUrl : "settings.html",
            controller : "settingsCtrl",
            activeTab: 'settings'
        })
      .otherwise("/");


    // use the HTML5 History API
    $locationProvider.html5Mode(true);
});

/* Custom service for consistent behaviors */
app.service('gHandler', function() {
	this.responseError = function (response) {
        // handle http error response: { data, status, statusText, headers, config }
        console.log("response error: " + JSON.stringify(response));
		//console.log("response error: " + response.status + " " + response.statusText);
		//console.log("  headers: " + response.headers);
	}
});

/* The Main controller has always viewable and accessible information like username, search function, etc */
app.controller("mainCtrl", function ($scope, $http, gHandler) {
	/* Get the Data for the main page */
    //$scope.deviceData;
    $scope.activeTab = 'dashboard';

    $http.get("/api/sessiondata")
        .then(function (response) {
        	$scope.session = response.data;
        }, function errorCallback(response) {
            console.log("Error getting Main Controller data: " + JSON.stringify(response));
        });
});

/* DASHBOARD */
app.controller("dashboardCtrl", function ($scope, $http, $interval, gHandler) {
	$scope.$parent.activeTab = 'dashboard';

	/* Get the Data for the dashboard */
	$scope.getData = function() {
		$http.get("/api/dashboard")
			.then(function (response) {
				$scope.data = response.data;
				$scope.updated = Date.now();
				dashboardRedraw(response.data);
			}, function errorCallback(response) {
				console.log("Error getting dashboard data: " + JSON.stringify(response));
			});
	};
	$scope.getData();

	//timer to collect the data
	var collectInterval = $interval(function(){$scope.getData();}, 5000); //delay in milliseconds
	$scope.stopCollection = function() { if (angular.isDefined(collectInterval)) { $interval.cancel(collectInterval);collectInterval = undefined;}};
	$scope.$on('$destroy', function() {$scope.stopCollection();});


});

/* SETTINGS */
app.controller("settingsCtrl", function ($scope, $http, $interval, $anchorScroll, gHandler) {
    $scope.$parent.activeTab = 'settings';
    $anchorScroll();
});

/* DEVICE LIST */
app.controller("devicesCtrl", function ($scope, $http, $interval, gHandler) {
	$scope.$parent.activeTab = 'devices';

	/* Get the Data  */
	$scope.getData = function() {
		$http.get("/api/devices")
			.then(function (response) {
				$scope.$parent.deviceData = response.data; /* put it in the parent class for better response and global search functionality */
				$scope.updated = Date.now();

			}, function errorCallback(response) {
				console.log("Error getting device list data: " + JSON.stringify(response));
			});
	};
	$scope.getData();

	$scope.sortBy = function(keyname){
			$scope.reverse = ($scope.sortKey === keyname ) ? !$scope.reverse : false; //if true make it false and vice versa
			$scope.sortKey = keyname;   //set the sortKey to the param passed
	};
	$scope.sortBy('name');

	//timer to collect the data
	/*
    var collectInterval = $interval(function(){$scope.getData();}, 5000); //delay in milliseconds
    $scope.stopCollection = function() { if (angular.isDefined(collectInterval)) { $interval.cancel(collectInterval);collectInterval = undefined;}};
    $scope.$on('$destroy', function() {$scope.stopCollection();});
    */

	/* Add Device */
	$scope.device = {};
	$scope.devLoading = false;
	$scope.postStatus = "";
	$scope.postStatusMsg = "";
	$scope.postDevice = function() {
		$scope.devLoading = true;
		$http.post("/api/devices/" + $scope.device.ip, $scope.device)
			.then(function (response) {
				$scope.devLoading = false;
				$scope.postStatusMsg = response.data;
				/* put it in the parent class for better response and global search functionality */
				$scope.postStatus = response.status === 200 ? "Success" : "Error " + response.status;

			}, function errorCallback(response) {
				console.log("Error posting data: " + JSON.stringify(response));
			});

	};

	$scope.devCheck = function() {
			var tmp = {};
			tmp = $scope.$parent.deviceData.find(function(dev){return dev.ip===$scope.device.ip});
			if (tmp) {
					$scope.device = JSON.parse(JSON.stringify(tmp));
					$scope.devMsg = "Loaded existing device '" + tmp.ip + "'";
			} else {
					$scope.devMsg = "";
			}
	};

});

/* DEVICE DETAIL */
app.controller("devicesDetailCtrl", function ($scope, $routeParams, $http, $interval, $anchorScroll, gHandler) {
	$scope.$parent.activeTab = 'devices';
	$scope.params = $routeParams;
	if ($scope.$parent.deviceData) $scope.devInfo = $scope.$parent.deviceData.find(function(dev){return dev.ip===$scope.params.deviceId});
	
	/* Get the Data  */
	$scope.getData = function() {
		$http.get("/api/devices/"+$scope.params.deviceId)
			.then(function (response) {
				$scope.devInfo = response.data.settings; //.settings, .data
				$scope.devData = response.data.data;
				$scope.updated = Date.now();
				
			}, function errorCallback(response) {
				console.log("Error getting device list data: " + JSON.stringify(response));
			});
	};
	$scope.getData();

	$scope.getType = function(t) {return typeof t};

	//timer to collect the data
	//var collectInterval = $interval(function(){$scope.getData();}, 1000); //5s, delay in milliseconds
	//$scope.stopCollection = function() { if (angular.isDefined(collectInterval)) { $interval.cancel(collectInterval);collectInterval = undefined;}};
	//$scope.$on('$destroy', function() {$scope.stopCollection();});




	$anchorScroll();
});

/* LOGS AND ALERTS */
app.controller("logsCtrl", function ($scope, $http, $interval, gHandler) {
    $scope.$parent.activeTab = 'logs';
});

/* PROFILE */




/* GLOBAL NON-ANGULAR FUNCTIONS */

function goBack() {
    window.history.back();
}
