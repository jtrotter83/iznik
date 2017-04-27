function getVersion() {
    var version = 'NaN';
    var xhr = new XMLHttpRequest();
    xhr.open('GET', chrome.extension.getURL('manifest.json'), false);
    xhr.send(null);
    var manifest = JSON.parse(xhr.responseText);
    return manifest.version;
}

//var version = chrome.app.getDetails().version;
var version = getVersion();

var d = document.createElement('div');
d.id = 'modtoolschrome';
d.style.display = "none";
d.innerHTML = version;
document.body.appendChild(d);

chrome.runtime.onMessage.addListener(
    function (request, sender, sendResponse) {
        //console.log("Content script message received");
        //console.log(request);

        if (request.action == 'getreq') {
            var data = $('#modtoolsreq').text();
            //console.log("Got majax data " + data);
            sendResponse({request: data});
        } else if (request.action == 'storersp') {
            var data = $('#modtoolsrsp').text();
            //console.log("Got majax data " + data);
            var rsp = JSON.stringify(request.data);
            //console.log("Store response len " + rsp.length);
            $('#modtoolsrsp').text(rsp);
            sendResponse({});
        }
    }
);

console.log("ModTools Content Script Loaded");

function keyText(text) {
    for (var i = 0; i < text.length; i++) {
        var e = new Event("keyup");
        var char = text.substring(i, i+1);
        console.log("Key", char);
        e.key=char;
        e.keyCode=e.key.charCodeAt(0);
        e.which=e.keyCode;
        e.altKey=false;
        e.ctrlKey=false;
        e.shiftKey=false;
        e.metaKey=false;
        // e.bubbles=true;
        // e.isTrusted = true;
        document.dispatchEvent(e);
    }
}

(function( $ ) {
    $.fn.execInsertText = function(text) {
        var activeElement = document.activeElement;
        var result = this.each(function() {
            this.focus();
            document.execCommand('selectAll');
            document.execCommand('insertText', false, text);
        });
        if (activeElement) {
            activeElement.focus();
        }
        return result;
    };
}( jQuery ));

function waitFor(check, parm) {
    var p = new Promise(function(resolve, reject) {
        function checkIt() {
            if (check()) {
                resolve(parm);
            } else {
                window.setTimeout(checkIt, 100);
            }
        }

        checkIt();
    });

    return(p);
}

function status(str) {
    $('#mtstatus').html(str);
}

function statusHide() {
    $('#mtholder').hide();
}

$(document).ready(function() {
    console.log("MT Document ready", document.URL);

    if (document.URL.indexOf('modtools.org') != -1) {
        // We are loading on a ModTools page.  Find out who we are; when we load on Facebook we use this info.
        $.ajax({
            url: '/api/session',
            type: 'GET',
            success: function (ret) {
                console.log("Logged in", ret);
                if (ret.ret === 0) {
                    if (ret.hasOwnProperty('me')) {
                        chrome.storage.sync.set({
                            'myid': ret.me.id,
                            'groups': ret.me.groups
                        });
                    }
                }
            }
        });
    } else if (document.URL.indexOf('https://www.facebook.com') === 0) {
        // We are loading on Facebook.  Find out who we are.
        chrome.storage.sync.get(null, function(obj) {
            console.log("MT ID", obj);
            var myid = obj.myid;

            if (myid) {
                // Put our status div in the bottom left
                $('body').append('<div style="position: fixed; bottom: 0; left: 0; background: #e8fefb" id="mtholder"><table><tbody><tr><td><img src="https://modtools.org/images/modtools_logo.png" width="40px" /></td><td id="mtstatus"></td></tr></tbody></table>')
                status('Checking...');

                // We want to post any outstanding messages to Facebook.
                $.ajax({
                    url: 'https://iznik.ilovefreegle.org/api/messages?groupid=21589&facebook_postable=true',
                    type: 'GET',
                    success:function(ret) {
                        console.log("Got unposted", ret);

                        if (ret.messages.length > 0) {
                            var confirmed = false;

                            try {
                                confirmed = localStorage.getItem('mtconfirmed');
                            } catch (e) {
                                console.log("LS exception", e.message);
                            }

                            var fbgroup = 'https://www.facebook.com/groups/91145196504/';

                            if (!confirmed) {
                                status('Asking...');
                                confirmed = window.confirm("May ModTools post " + ret.messages.length + " post" + ((ret.messages.length != 1) ? 's' : '') + '?');
                                try {
                                    localStorage.setItem('mtconfirmed', confirmed);
                                } catch (e) {
                                    console.log("LS exception", e.message);
                                }
                            }

                            if (confirmed) {
                                status('Posting ' + ret.messages.length + '...');
                                var url = document.URL;

                                if (document.URL === fbgroup) {
                                    // We're already there - post them.
                                    var messages = ret.messages;

                                    waitFor(function() {
                                        var ret = false;
                                        $('input').each(function() {
                                            var placeholder = $(this).prop('placeholder');
                                            if (placeholder == 'What are you selling?') {
                                                ret = true;
                                            }
                                        });

                                        return(ret);
                                    }, messages).then(function(messages) {
                                        $('input').each(function() {
                                            var placeholder = $(this).prop('placeholder');
                                            if (placeholder == 'What are you selling?') {
                                                var inp = this;
                                                $(inp).click();
                                                $(inp).focus();

                                                waitFor(function() {
                                                    var ret = false;
                                                    $('input').each(function() {
                                                        var placeholder = $(this).prop('placeholder');
                                                        if (placeholder == 'Add price') {
                                                            ret = true;
                                                        }
                                                    });

                                                    return(ret);
                                                }, messages).then(function(messages) {
                                                    var message = messages.unshift();
                                                    console.log("Post", message);

                                                    $('input').each(function() {
                                                        var placeholder = $(this).prop('placeholder');
                                                        if (placeholder == 'What are you selling?') {
                                                            $(this).execInsertText(message.subject);
                                                        }

                                                        if (placeholder == 'Add price') {
                                                            $(this).execInsertText(0);
                                                        }

                                                        if (placeholder == 'Add location (optional)') {
                                                            try {
                                                                var next = $(this).closest('div').find('button');
                                                                if (next.prop('title') == 'Remove') {
                                                                    // Click to remove default location.
                                                                    next.click();
                                                                }

                                                                var loc = this;
                                                                window.setTimeout(function() {
                                                                    if (message.hasOwnProperty('area')) {
                                                                        $(loc).execInsertText(message.area.name);
                                                                    }
                                                                    $(loc).blur();

                                                                    var url = "https://modtools.org/message/" + message.id + "?src=fbgroup2";
                                                                    var inp = $('#composer_text_input_box').find('div[contenteditable=true]');
                                                                    inp.focus();

                                                                    window.setTimeout(function () {
                                                                        document.execCommand("insertHTML", false, "Click to read and reply: " + url + ' ');
                                                                        document.execCommand("undo", false);

                                                                        window.setTimeout(function() {
                                                                            $.ajax({
                                                                                url: 'https://modtools.org/api/messages?groupid=21589&facebook_postable=true',
                                                                                type: 'POST',
                                                                                data: {
                                                                                    'action': 'UpdateFacebookPostable',
                                                                                    'id': message.id,
                                                                                    'arrival': message.arrival
                                                                                },
                                                                                success: function (ret) {
                                                                                    try {
                                                                                        localStorage.removeItem('mtconfirmed');
                                                                                    } catch (e) {}

                                                                                    $('#pagelet_group_composer button').click();
                                                                                }
                                                                            });
                                                                        }, 3000);
                                                                    }, 5000)
                                                                }, 1000);
                                                            } catch (e) {
                                                                console.log("Failed on location", e.message);
                                                            }

                                                        }

                                                        // $('#pagelet_group_composer button').each(function() {
                                                        //     if ($(this).prop('disabled')) {
                                                        //         $(this).prop('disabled', null);
                                                        //     }
                                                        // });
                                                    });
                                                });
                                            }
                                        });
                                    });


                                    // Find the status selector.
                                    // $('a').each(function() {
                                    //     var tooltip = $(this).data('tooltip-content');
                                    //     if (tooltip == 'Start Discussion') {
                                    //         console.log("Switch to dicussion", $(this));
                                    //         $(this).click();
                                    //         window.setTimeout(function() {
                                    //             $('input').each(function() {
                                    //                 var placeholder = $(this).prop('placeholder');
                                    //                 console.log("Placeholder", placeholder);
                                    //                 if (placeholder == 'What are you selling?') {
                                    //                     console.log("Found input");
                                    //                     $(this).val("https://modtools.org/message/" + messages[messageIndex++].id);
                                    //                 }
                                    //             })
                                    //         }, 1000);
                                    //     }
                                    // })
                                } else {
                                    // We need to navigate.
                                    console.log("Need to navigate");
                                    document.location = fbgroup;
                                }
                            } else {
                                statusHide();
                            }
                        } else {
                            status('Nothing to do');
                            statusHide();
                        }
                    }
                })
            } else {
                // We don't know who we are yet - we mustn't have yet loaded MT with this plugin.
                status('<font color="red">Please log in to ModTools</font>');
            }
        });
    }
});