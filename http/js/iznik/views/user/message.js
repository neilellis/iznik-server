define([
    'jquery',
    'underscore',
    'backbone',
    'iznik/base',
    'iznik/views/infinite',
    'iznik/views/chat/chat'
], function($, _, Backbone, Iznik) {
    Iznik.Views.User.Message = Iznik.View.extend({
        className: "marginbotsm botspace",

        events: {
            'click .js-caret': 'carettoggle',
            'click .js-fop': 'fop'
        },

        expanded: false,

        caretshow: function() {
            if (!this.expanded) {
                this.$('.js-replycount').addClass('reallyHide');
                this.$('.js-unreadcountholder').addClass('reallyHide');
                this.$('.js-promised').addClass('reallyHide');
                this.$('.js-caretdown').show();
                this.$('.js-caretup').hide();
            } else {
                this.$('.js-replycount').removeClass('reallyHide');
                this.$('.js-unreadcountholder').removeClass('reallyHide');
                this.$('.js-promised').removeClass('reallyHide');
                this.$('.js-caretdown').hide();
                this.$('.js-caretup').show();
            }
        },

        expand: function() {
            this.$('.js-caretdown').click();
        },

        setReply: function(text) {
            var self = this;
            console.log("Set reply", text);

            try {
                // Clear the local storage, so that we don't get stuck here.
                localStorage.removeItem('replyto');
                localStorage.removeItem('replytext');
            } catch (e) {}

            this.$('.js-replytext').val(text);

            // We might get called back twice because of the html, body selector (which we need for browser compatibility)
            // so make sure we only actually click send once.
            self.readyToSend = true;

            $('html, body').animate({
                    scrollTop: self.$('.js-replytext').offset().top
                },
                2000,
                function() {
                    console.log("Try to send", self.readyToSend);
                    if (self.readyToSend) {
                        // Now send it.
                        self.readyToSend = false;
                        self.$('.js-send').click();
                    }
                }
            );
        },

        carettoggle: function() {
            this.expanded = !this.expanded;
            if (this.expanded) {
                this.$('.js-snippet').slideUp();
            } else {
                this.$('.js-snippet').slideDown();
            }
            this.caretshow();
        },

        fop: function() {
            var v = new Iznik.Views.Modal();
            v.open('user_home_fop');
        },

        updateReplies: function() {
            if (this.replies.length == 0) {
                this.$('.js-noreplies').fadeIn('slow');
            } else {
                this.$('.js-noreplies').hide();
            }
        },

        updateUnread: function() {
            var self = this;
            var unread = 0;

            // We might or might not have the chats, depending on whether we're logged in at this point.
            if (Iznik.Session.hasOwnProperty('chats')) {
                Iznik.Session.chats.each(function(chat) {
                    var refmsgids = chat.get('refmsgids');
                    _.each(refmsgids, function(refmsgid) {
                        if (refmsgid == self.model.get('id')) {
                            var thisun = chat.get('unseen');
                            unread += thisun;

                            if (thisun > 0) {
                                // This chat might indicate a new replier we've not got listed.
                                // TODO Could make this perform better than doing a full fetch.
                                self.model.fetch().then(function() {
                                    self.replies.add(self.model.get('replies'));
                                    self.updateReplies();
                                });
                            }
                        }
                    });
                });
            }

            if (unread > 0) {
                this.$('.js-unreadcount').html(unread);
                this.$('.js-unreadcountholder').show();
            } else {
                this.$('.js-unreadcountholder').hide();
            }
        },

        watchChatRooms: function() {
            var self = this;

            if (this.inDOM() && Iznik.Session.hasOwnProperty('chats')) {
                // If the number of unread messages relating to this message changes, we want to flag it in the count.  So
                // look for chats which refer to this message.  Note that chats can refer to multiple.
                Iznik.Session.chats.fetch().then(function() {
                    Iznik.Session.chats.each(function (chat) {
                        self.listenTo(chat, 'change:unseen', self.updateUnread);
                    });

                    self.updateUnread();

                    self.listenToOnce(Iznik.Session.chats, 'newroom', self.watchChatRooms);
                });
            }
        },

        stripGumf: function(property) {
            // We have the same function in PHP in Message.php; keep them in sync.
            var text = this.model.get(property);

            if (text) {
                // console.log("Strip photo", text);
                // Strip photo links - we should have those as attachments.
                text = text.replace(/You can see a photo[\s\S]*?jpg/, '');
                text = text.replace(/Check out the pictures[\s\S]*?https:\/\/trashnothing[\s\S]*?pics\/\d*/, '');
                text = text.replace(/You can see photos here[\s\S]*jpg/m, '');
                text = text.replace(/https:\/\/direct.*jpg/m, '');

                // FOPs
                text = text.replace(/Fair Offer Policy applies \(see https:\/\/[\s\S]*\)/, '');
                text = text.replace(/Fair Offer Policy:[\s\S]*?reply./, '');

                // App footer
                text = text.replace(/Freegle app.*[0-9]$/m, '');

                // Footers
                text = text.replace(/--[\s\S]*Get Freegling[\s\S]*book/m, '');
                text = text.replace(/--[\s\S]*Get Freegling[\s\S]*org[\s\S]*?<\/a>/m, '');
                text = text.replace(/This message was sent via Freegle Direct[\s\S]*/m, '');
                text = text.replace(/\[Non-text portions of this message have been removed\]/m, '');
                text = text.replace(/^--$[\s\S]*/m, '');

                // Redundant line breaks
                text = text.replace(/(?:(?:\r\n|\r|\n)\s*){2}/m, "\n\n");

                text = text.trim();
                // console.log("Stripped photo", text);
            } else {
                text = '';
            }

            this.model.set(property, text);
        },

        render: function() {
            var self = this;

            var outcomes = self.model.get('outcomes');
            if (outcomes && outcomes.length > 0) {
                // Hide completed posts by default.
                self.$el.hide();
            }

            this.stripGumf('textbody');

            // The server will have returned us a snippet.  But if we've stripped out the gumf and we have something
            // short, use that instead.
            var tb = this.model.get('textbody');
            if (tb.length < 60) {
                this.model.set('snippet', tb);
            }

            var p = Iznik.View.prototype.render.call(self);
            p.then(function() {
                if (self.expanded) {
                    self.$('.panel-collapse').collapse('show');
                } else {
                    self.$('.panel-collapse').collapse('hide');
                }

                var groups = self.model.get('groups');
                self.$('.js-groups').empty();
                _.each(groups, function(group) {
                    var v = new Iznik.Views.User.Message.Group({
                        model: new Iznik.Model(group)
                    });
                    v.render();
                    self.$('.js-groups').append(v.el);
                });

                self.$('.js-attlist').empty();
                var photos = self.model.get('attachments');

                var v = new Iznik.Views.User.Message.Photos({
                    collection: new Iznik.Collection(photos),
                    subject: self.model.get('subject')
                });
                v.render().then(function() {
                    self.$('.js-attlist').append(v.el);
                });

                var replies = self.model.get('replies');
                self.replies = new Iznik.Collection(replies);
                console.log("Check replies", replies);

                if (replies.length > 0) {
                    // Show and update the reply details.
                    if (replies.length > 0) {
                        self.$('.js-noreplies').hide();
                        self.$('.js-replies').empty();
                        self.listenTo(self.model, 'change:replies', self.updateReplies);
                        self.updateReplies();

                        self.repliesView = new Backbone.CollectionView({
                            el: self.$('.js-replies'),
                            modelView: Iznik.Views.User.Message.Reply,
                            modelViewOptions: {
                                collection: self.replies,
                                message: self.model,
                                offers: self.options.offers
                            },
                            collection: self.replies
                        });

                        self.repliesView.render();

                        // We might have been asked to open up one of these messages because we're showing the corresponding
                        // chat.
                        if (self.options.chatid ) {
                            var model = self.replies.get(self.options.chatid);
                            console.log("Get chat model", model);
                            if (model) {
                                var view = self.repliesView.viewManager.findByModel(model);
                                console.log("Got view", view, view.$('.js-caret'));
                                // Slightly hackily jump up to find the owning message and click to expand.
                                view.$el.closest('.panel-heading').find('.js-caret').click();
                            }
                            self.replies.each(function(reply) {
                                console.log("Compare", reply.get('chatid'), self.options.chatid);
                                if (reply.get('chatid') == self.options.chatid) {
                                    console.log("Found it");
                                }
                            });
                        }
                    } else {
                        self.$('.js-noreplies').show();
                    }
                }

                self.updateUnread();

                // We want to keep an eye on chat messages, because those which are in conversations referring to our
                // message should affect the counts we display.
                self.watchChatRooms();

                // If the number of promises changes, then we want to update what we display.
                self.listenTo(self.model, 'change:promisecount', self.render);

                // By adding this at the end we avoid border flicker.
                self.$el.addClass('panel panel-info');
            });

            return(p);
        }
    });

    Iznik.Views.User.Message.Group = Iznik.View.extend({
        template: "user_message_group",

        render: function() {
            var self = this;
            var p = Iznik.View.prototype.render.call(this);
            p.then(function(self) {
                self.$('.timeago').timeago();
            });
            return(p);
        }
    });

    Iznik.Views.User.Message.Photo = Iznik.View.extend({
        tagName: 'li',

        events: {
            'click': 'zoom'
        },
        
        template: 'user_message_photo',

        zoom: function (e) {
            e.preventDefault();
            e.stopPropagation();

            var v = new Iznik.Views.User.Message.PhotoZoom({
                model: this.model
            });
            v.render();
        }
    });

    Iznik.Views.User.Message.PhotoZoom = Iznik.Views.Modal.extend({
        template: 'user_message_photozoom'
    });

    Iznik.Views.User.Message.Photos = Iznik.View.extend({
        template: 'user_message_photos',

        offset: 0,

        nextPhoto: function() {
            var self = this;
            self.currentPhoto.fadeOut('slow', function() {
                self.offset++;
                self.offset = self.offset % self.photos.length;
                self.currentPhoto = self.photos[self.offset];
                self.currentPhoto.fadeIn('slow', function() {
                    _.delay(_.bind(self.nextPhoto, self), 10000);
                })
            })
        },

        render: function() {
            var self = this;
            var len = self.collection.length;

            // If we have multiple photos, then we cycle through each of them, fading in and out.  This reduces the
            // screen space, but still allows people to see all of them.
            var p = Iznik.View.prototype.render.call(this);
            p.then(function() {
                self.photos = [];
                self.collection.each(function(att) {
                    att.set('subject', self.options.subject);

                    var v = new Iznik.Views.User.Message.Photo({
                        model: att
                    });
                    v.render().then(function() {
                        self.$('.js-photos').append(v.$el);
                    });

                    self.photos.push(v.$el);

                    if (self.photos.length > 1) {
                        v.$el.hide();
                    } else {
                        self.currentPhoto = v.$el;
                    }
                });

                if (self.photos.length > 1) {
                    _.delay(_.bind(self.nextPhoto, self), 10000);
                }
            });

            return(p);
        }
    });

    Iznik.Views.User.Message.Reply = Iznik.View.extend({
        tagName: 'li',

        template: 'user_message_reply',

        className: 'message-reply',

        events: {
            'click .js-chat': 'dm',
            'click .js-promise': 'promise',
            'click .js-renege': 'renege'
        },

        dm: function() {
            var self = this;
            require(['iznik/views/chat/chat'], function(ChatHolder) {
                ChatHolder().openChat(self.model.get('user').id);
            })
        },

        promise: function() {
            var self = this;

            var v = new Iznik.Views.User.Message.Promise({
                model: new Iznik.Model({
                    message: self.options.message.toJSON2(),
                    user: self.model.get('user')
                }),
                offers: self.options.offers
            });

            self.listenToOnce(v, 'promised', function() {
                self.options.message.fetch().then(function() {
                    self.render.call(self, self.options);
                })
            });

            v.render();
        },

        renege: function() {
            var self = this;

            var v = new Iznik.Views.Confirm({
                model: self.model
            });
            v.template = 'user_message_renege';

            self.listenToOnce(v, 'confirmed', function() {
                $.ajax({
                    url: API + 'message/' + self.options.message.get('id'),
                    type: 'POST',
                    data: {
                        action: 'Renege',
                        userid: self.model.get('user').id
                    }, success: function() {
                        self.options.message.fetch().then(function() {
                            self.render.call(self, self.options);
                        });
                    }
                })
            });

            v.render();
        },

        chatPromised: function() {
            var self = this;
            self.model.set('promised', true);
            self.render();
        },

        render: function() {
            var self = this;
            var p = Iznik.View.prototype.render.call(self).then(function(self) {
                var chat = Iznik.Session.chats.get({
                    id: self.model.get('chatid')
                });

                // We might not find this chat if the user has closed it.
                console.log("Find chat for message reply", chat, self);

                if (!_.isUndefined(chat)) {
                    // If the number of unseen messages in this chat changes, update this view so that the count is
                    // displayed here.
                    self.listenToOnce(chat, 'change:unseen', self.render);
                    self.model.set('unseen', chat.get('unseen'));
                    self.model.set('message', self.options.message.toJSON2());
                    self.model.set('me', Iznik.Session.get('me'));
                    p = Iznik.View.prototype.render.call(self).then(function() {
                        self.$('.timeago').timeago();
                    });

                    // We might promise to this person from a chat.
                    self.listenTo(chat, 'promised', _.bind(self.chatPromised, self));
                }
            });

            return(p);
        }
    });

    Iznik.Views.User.Message.Promise = Iznik.Views.Confirm.extend({
        template: 'user_message_promise',

        promised: function() {
            var self = this;

            $.ajax({
                url: API + 'message/' + self.model.get('message').id,
                type: 'POST',
                data: {
                    action: 'Promise',
                    userid: self.model.get('user').id
                }, success: function() {
                    self.trigger('promised')
                }
            })
        },

        render: function() {
            var self = this;
            this.listenToOnce(this, 'confirmed', this.promised);
            var p = this.open(this.template);
            p.then(function() {
                var msgid = self.model.get('message').id;

                self.options.offers.each(function(offer) {
                    self.$('.js-offers').append('<option value="' + offer.get('id') + '" />');
                    self.$('.js-offers option:last').html(offer.get('subject'));
                });

                self.$('.js-offers').val(msgid);

            });

            return(p);
        }
    });

    Iznik.Views.User.Message.Replyable = Iznik.Views.User.Message.extend({
        template: 'user_message_replyable',

        events: {
            'click .js-send': 'send',
            'click .js-mapzoom': 'mapZoom'
        },

        initialize: function(){
            this.events = _.extend(this.events, Iznik.Views.User.Message.prototype.events);
        },

        mapZoom: function(e) {
            e.preventDefault();
            e.stopPropagation();

            var self = this;
            var v = new Iznik.Views.User.Message.Map({
                model: self.model
            });

            v.render();
        },

        wordify: function (str) {
            str = str.replace(/\b(\w*)/g, "<span>$1</span>");
            return (str);
        },

        startChat: function() {
            // We start a conversation with the sender.
            var self = this;

            self.wait = new Iznik.Views.PleaseWait();
            self.wait.render();

            $.ajax({
                type: 'PUT',
                url: API + 'chat/rooms',
                data: {
                    userid: self.model.get('fromuser').id
                }, success: function(ret) {
                    if (ret.ret == 0) {
                        var chatid = ret.id;
                        var msg = self.$('.js-replytext').val();

                        $.ajax({
                            type: 'POST',
                            url: API + 'chat/rooms/' + chatid + '/messages',
                            data: {
                                message: msg,
                                refmsgid: self.model.get('id')
                            }, complete: function() {
                                // Ensure the chat is opened, which shows the user what will happen next.
                                Iznik.Session.chats.fetch().then(function() {
                                    self.$('.js-replybox').slideUp();
                                    var chatmodel = Iznik.Session.chats.get(chatid);
                                    var chatView = Iznik.activeChats.viewManager.findByModel(chatmodel);
                                    chatView.restore();
                                    self.wait.close();
                                });
                            }
                        });
                    }
                }
            })
        },

        send: function() {
            var self = this;
            var replytext = self.$('.js-replytext').val();
            console.log("Send reply", replytext);

            if (replytext.length == 0) {
                self.$('.js-replytext').addClass('error-border').focus();
            } else {
                self.$('.js-replytext').removeClass('error-border');

                try {
                    // Save off details of our reply.  This is so that when we do a force login and may have to sign up or
                    // log in, which can cause a page refresh, we will repopulate this data during the render.
                    localStorage.setItem('replyto', self.model.get('id'));
                    localStorage.setItem('replytext', replytext);
                } catch (e) {}

                // If we're not already logged in, we want to be.
                self.listenToOnce(Iznik.Session, 'loggedIn', function () {
                    // Now we're logged in we no longer need the local storage of the reply, because we've put it
                    // back into the DOM during the render.
                    try {
                        // Clear the local storage, so that we don't get stuck here.
                        localStorage.removeItem('replyto');
                        localStorage.removeItem('replytext');
                    } catch (e) {}

                    // When we reply to a message on a group, we join the group if we're not already a member.
                    var memberofs = Iznik.Session.get('groups');
                    var member = false;
                    var tojoin = null;
                    if (memberofs) {
                        memberofs.each(function(memberof) {
                            var msggroups = self.model.get('groups');
                            _.each(msggroups, function(msggroup) {
                                if (memberof.id = msggroup.groupid) {
                                    member = true;
                                }
                            });
                        });
                    }

                    if (!member) {
                        // We're not a member of any groups on which this message appears.  Join one.  Doesn't much
                        // matter which.
                        var tojoin = self.model.get('groups')[0].id;
                        $.ajax({
                            url: API + 'memberships',
                            type: 'PUT',
                            data: {
                                groupid : tojoin
                            }, success: function(ret) {
                                if (ret.ret == 0) {
                                    // We're now a member of the group.  Fetch the message back, because we'll see more
                                    // info about it now.
                                    self.model.fetch().then(function() {
                                        self.startChat();
                                    })
                                } else {
                                    // TODO
                                }
                            }, error: function() {
                                // TODO
                            }
                        })
                    } else {
                        console.log("We're already a member");
                        self.startChat();
                    }
                });

                Iznik.Session.forceLogin({
                    modtools: false
                });
            }
        },

        render: function() {
            var self = this;
            var p;

            if (self.rendered) {
                p = resolvedPromise(self);
            } else {
                self.rendered = true;
                var mylocation = null;
                try {
                    mylocation = localStorage.getItem('mylocation');

                    if (mylocation) {
                        mylocation = JSON.parse(mylocation);
                    }
                } catch (e) {
                }

                this.model.set('mylocation', mylocation);

                // Static map custom markers don't support SSL.
                this.model.set('mapicon', 'http://' + window.location.hostname + '/images/mapareamarker.png');

                // Hide until we've got a bit into the render otherwise the border shows.
                this.$el.css('visibility', 'hidden');
                p = Iznik.Views.User.Message.prototype.render.call(this);

                p.then(function() {
                    // We handle the subject as a special case rather than a template expansion.  We might be doing a search, in
                    // which case we want to highlight the matched words.  So we split out the subject string into a sequence of
                    // spans, which then allows us to highlight any matched ones.
                    self.$('.js-subject').html(self.wordify(self.model.get('subject')));
                    var matched = self.model.get('matchedon');
                    if (matched) {
                        self.$('.js-subject span').each(function () {
                            if ($(this).html().toLowerCase().indexOf(matched.word) != -1) {
                                $(this).addClass('searchmatch');
                            }
                        });
                    }

                    if (self.model.get('mine')) {
                        // Stop people replying to their own messages.
                        self.$('.js-replybox').hide();
                    } else {
                        // We might have been trying to reply.
                        try {
                            var replyto = localStorage.getItem('replyto');
                            var replytext = localStorage.getItem('replytext');
                            var thisid = self.model.get('id');

                            if (replyto == thisid) {
                                self.setReply.call(self, replytext);
                            }
                        } catch (e) {console.log("Failed", e)}
                    }

                    self.$el.css('visibility', 'visible');
                })
            }

            return(p);
        }
    });

    Iznik.Views.User.Message.Map = Iznik.Views.Modal.extend({
        template: 'user_message_mapzoom',

        render: function() {
            var self = this;
            var p = Iznik.Views.Modal.prototype.render.call(self);
            p.then(function() {
                require(['gmaps'], function() {
                    self.waitDOM(self, function(self) {
                        // Set map to be square - will have height 0 when we open.
                        var map = self.$('.js-map');
                        var mapWidth = map.width();
                        console.log("Width", mapWidth);
                        map.height(mapWidth);

                        var location = self.model.get('location');
                        var area = self.model.get('area');
                        var centre = null;

                        if (location) {
                            centre = new google.maps.LatLng(location.lat, location.lng);
                        } else if (area) {
                            centre = new google.maps.LatLng(area.lat, area.lng);
                            self.$('.js-vague').show();
                        }

                        var mapOptions = {
                            mapTypeControl      : false,
                            streetViewControl   : false,
                            center              : centre,
                            panControl          : mapWidth > 400,
                            zoomControl         : mapWidth > 400,
                            zoom                : self.model.get('zoom') ? self.model.get('zoom') : 16
                        };

                        self.map = new google.maps.Map(map.get()[0], mapOptions);

                        var icon = {
                            url: '/images/user_logo.png',
                            scaledSize: new google.maps.Size(50, 50),
                            origin: new google.maps.Point(0,0),
                            anchor: new google.maps.Point(0, 0)
                        };

                        var marker = new google.maps.Marker({
                            position: centre,
                            icon: icon,
                            map: self.map
                        });
                    });
                });
            });

            return(p);
        }
    });
});