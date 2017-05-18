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
            controller : "dashboardCtrl"
        })
        .when("/settings", {
            templateUrl : "settings.html",
            controller : "settingsCtrl"
        })
        .when("/devices", {
            templateUrl : "device-list.html",
            controller : "devicesCtrl"
        })
        .when("/devices/:deviceId", {
            templateUrl : "device-detail.html",
            controller : "devicesDetailCtrl"
        })
        .when("/logs", {
            templateUrl : "logs.html",
            controller : "logsCtrl"
        });

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
    var deviceData;

    $http.get("/api/sessiondata")
        .then(function (response) {
        	$scope.session = response.data;
        }, function errorCallback(response) {
            console.log("Error getting Main Controller data: " + JSON.stringify(response));
        });
});
app.controller("dashboardCtrl", function ($scope, $http, $interval, gHandler) {
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
    }
    $scope.getData();

	//timer to collect the data
    var collectInterval = $interval(function(){$scope.getData();}, 5000); //delay in milliseconds
    $scope.stopCollection = function() { if (angular.isDefined(collectInterval)) { $interval.cancel(collectInterval);collectInterval = undefined;}};
    $scope.$on('$destroy', function() {$scope.stopCollection();});


});

app.controller("settingsCtrl", function ($scope, $http, $interval, gHandler) {
    $scope.msg = "I love London";
});

app.controller("devicesCtrl", function ($scope, $http, $interval, gHandler) {
	/* Get the Data  */
	//if ($scope.$parent.deviceData) {updateData($scope.$parent.deviceData)}

    $scope.getData = function() {
        $http.get("/api/devices")
            .then(function (response) {
                $scope.devData = response.data;
                $scope.updated = Date.now();
                //updateData(response.data);
            }, function errorCallback(response) {
                console.log("Error getting device list data: " + JSON.stringify(response));
            });
    }
    $scope.getData();

    $scope.sort = function(keyname){
        $scope.sortKey = keyname;   //set the sortKey to the param passed
        $scope.reverse = !$scope.reverse; //if true make it false and vice versa
    }
    $scope.devSortType     = 'name'; // set the default sort type
    $scope.devSortReverse  = false;  // set the default sort order
    $scope.devSearch   = '';     // set the default search/filter term

    // create the list of sushi rolls
    $scope.devData = [
        { name: 'Cali Roll', fish: 'Crab', tastiness: 2 },
        { name: 'Philly', fish: 'Tuna', tastiness: 4 },
        { name: 'Tiger', fish: 'Eel', tastiness: 7 },
        { name: 'Rainbow', fish: 'Variety', tastiness: 6 }
    ];

    //timer to collect the data
	/*
    var collectInterval = $interval(function(){$scope.getData();}, 5000); //delay in milliseconds
    $scope.stopCollection = function() { if (angular.isDefined(collectInterval)) { $interval.cancel(collectInterval);collectInterval = undefined;}};
    $scope.$on('$destroy', function() {$scope.stopCollection();});
    */
});
app.controller("devicesDetailCtrl", function ($scope, $http, $interval, gHandler) {
    $scope.id = "I love Paris";
});


