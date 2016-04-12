define([
    'jquery',
    'underscore',
    'backbone',
    'iznik/base'
], function($, _, Backbone, Iznik) {

    Iznik.Views.GoogleLoad = Iznik.View.extend({
        authResult: undefined,

        disabled: false,

        onSignInCallback: function (authResult) {
            var self = this;
            console.log("onSignInCallback", authResult);

            function doIt(authResult) {
                self.authResult = authResult;
                $.ajax({
                    type: 'POST',
                    url: '/api/session',
                    data: {
                        'googleauthcode': self.authResult.code,
                        'googlelogin': true
                    },
                    success: function (result) {
                        window.location.reload();
                    }
                });
            }

            if (authResult['access_token']) {
                self.accessToken = authResult['access_token'];
                console.log("Signed in");
                // The user is signed in.  Pass the code to the server to allow it to get an access token.
                doIt(authResult);
            } else if (authResult['error']) {
                // TODO
                console.log('There was an error: ' + authResult['error']);
            }
        },

        signInButton: function (id) {
            try {
                var self = this;
                self.buttonId = id;
                self.scopes = "profile email";
                console.log("Set up sign in button", id, self.disabled);

                if (self.disabled) {
                    console.log("Google sign in disabled");
                    $('#' + id + ' img').addClass('signindisabled');
                } else {
                    console.log("Google sign in enabled");
                    $('#' + id + ' img').removeClass('signindisabled');
                    $('#' + id).click(function () {
                        // Get client id
                        self.clientId = $('meta[name=google-signin-client_id]').attr("content");

                        console.log("Log in");
                        // Custom signin button
                        var params = {
                            'clientid': self.clientId,
                            'cookiepolicy': 'single_host_origin',
                            'callback': self.onSignInCallback,
                            'immediate': false,
                            'scope': self.scopes
                        };

                        gapi.auth.signIn(params);
                    });
                }
            } catch (e) {
                console.log("Google API load failed", e);
            }
        },

        noop: function(authResult) {
            console.log("Noop", authResult)
            $('#googleshim').hide();
        },

        buttonShim: function (id) {
            try {
                gapi.signin.render(id, {
                    'clientid': self.clientId,
                    'cookiepolicy': 'single_host_origin',
                    'callback': self.noop,
                    'immediate': false,
                    'scope': self.scopes
                });
            } catch (e) {
                // Probably a blocker
                console.log("Google button shim failed", e);
                this.disabled = true;
            }
        },

        disconnectUser: function () {
            var self = this;
            var access_token = self.accessToken;
            var revokeUrl = 'https://accounts.google.com/o/oauth2/revoke?token=' +
                access_token;

            // Perform an asynchronous GET request.
            $.ajax({
                type: 'GET',
                url: revokeUrl,
                async: false,
                contentType: "application/json",
                dataType: 'jsonp',
                success: function (nullResponse) {
                    console.log("Revoked access token");
                },
                error: function (e) {
                    console.log("Revoke failed", e);
                }
            });
        }
    });
});