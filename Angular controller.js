define(['app'], function(app)
{
    'use strict';

    app.controller('JobsPublishController', [
        '$scope', '$rootScope', '$route', '$location', 'JobService', 'Notify', 'ApplicantService', 'UserService', 'BenchmarkService', '$modal', '$q', '$window',
        function($scope, $rootScope, $route, $location, JobService, Notify, ApplicantService, UserService, BenchmarkService, modal, $q, $window) {

            var params = $route.current.params;

            $scope.job = {
                positions: 1,
                extraPositions: 0
            };

            if (params.hasOwnProperty('id')) {
                $scope.job.id = params.id;
                $scope.jobId  = params.id;

                JobService.get(params.id)
                    .then(function (data) {

                        $scope.base_job_posting_price   = parseFloat($rootScope.config.base_job_posting_price);
                        $scope.extra_job_position_price = parseFloat($rootScope.config.extra_job_position_price);

                        $scope.totals = {
                            firstPosition:  $scope.base_job_posting_price,
                            extraPositions: 0,
                            total:          0
                        }

                        $scope.job.title          = data.title;
                        $scope.job.url            = data.url;
                        $scope.job.positions      = (data.positions && data.positions > 0) ? data.positions : 1;
                        $scope.job.basePositions  = (data.positions && data.positions > 0) ? data.positions : 1;
                        $scope.job.status         = data.status;

                        if ($scope.job.status != 'pending') {
                            $scope.totals.firstPosition = 0;
                        }
                        $scope.calculateTotals();
                    });
            }


            $scope.postJob = function (token) {
                var deferred = $q.defer();
                token = token || {};

                JobService.postJob({
                    jobId: $scope.job.id,
                    totalCost: parseFloat($scope.totals.total),
                    positions: parseInt($scope.job.positions),
                    token: token
                }).success(function (response) {
                    if (response.success) {
                        deferred.resolve();
                        Notify.success(response.message);
                        $location.path('jobs/edit/' + $scope.job.id);
                    } else {
                        Notify.error(response.data.message);
                        $('body').animate({scrollTop: 0}, 800);
                        deferred.reject();
                        $scope.loading = '';
                    }
                }).error(function (response) {
                    if (response && !response.success) {
                        Notify.error(response.data.message);
                        $('body').animate({scrollTop: 0}, 800);
                        deferred.reject();
                        $scope.loading = '';
                    }
                });
                return deferred.promise;
            };

            $scope.checkout = function () {
                $scope.loading = 'btn-loading';
                if ($scope.totals.total > 0) {
                    var isPaid = false;
                    var handler = StripeCheckout.configure({
                        key: $rootScope.config.stripePk,
                        name: "talentspot",
                        description: "talentspot job post",
                        image: "https://s3.amazonaws.com/talentspot-static/v3/_general/favicons/apple-touch-icon-180x180.png",
                        amount: $scope.totals.total * 100,
                        email: $rootScope.user.data.email,
                        token: function (token) {
                            isPaid = true;
                            $scope.postJob(token).then(function () {
                                $scope.loading = '';
                            });
                        },
                        closed: function () {
                            if (!isPaid) {
                                $scope.loading = '';
                            }
                        }
                    });
                    handler.open();

                } else {
                    $scope.postJob();
                }

            };

            $scope.editJob = function(jobId){
                $location.path('/jobs/edit/'+jobId);
            };

            $scope.calculateTotals = function(){
                // Recalculate billable positions
                $scope.job.extraPositions = parseInt(parseInt($scope.job.positions) - parseInt($scope.job.basePositions));
                // Recalculate cost
                $scope.totals.extraPositions = parseInt($scope.job.extraPositions ? $scope.job.extraPositions : 0) * $scope.extra_job_position_price;
                $scope.totals.total          = parseFloat($scope.totals.extraPositions) + parseFloat($scope.totals.firstPosition);
            }
        }]);
});