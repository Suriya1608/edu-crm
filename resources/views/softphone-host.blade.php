<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TCN Softphone Host</title>
    <style>
        html, body {
            width: 100%;
            height: 100%;
            margin: 0;
            overflow: hidden;
            background: #e2e8f0;
        }

        #softphoneHostFrame {
            width: 100%;
            height: 100%;
            border: 0;
            display: block;
            background: #ffffff;
        }
    </style>
</head>
<body>
    <iframe
        id="softphoneHostFrame"
        src="{{ route('softphone') }}"
        title="TCN Softphone"
        allow="microphone; autoplay"
    ></iframe>

    <script>
    (function () {
        'use strict';

        var origin = window.location.origin;
        var frame = document.getElementById('softphoneHostFrame');
        var childLoaded = false;
        var childWindow = null;
        var pending = [];
        var clients = [];
        var snapshot = {
            type: 'TCN_STATE_SYNC',
            state: 'connecting',
            phone: '',
            paused: false,
            muted: false,
            onHold: false,
            minimized: false,
            tcnStatus: '',
            callActive: false,
            callLogId: null,
            callEstablishedAt: null
        };

        function post(target, message) {
            if (!target) return;
            try {
                target.postMessage(message, origin);
            } catch (_) {}
        }

        function pruneClients() {
            clients = clients.filter(function (client) {
                try {
                    return client && !client.closed;
                } catch (_) {
                    return false;
                }
            });
        }

        function addClient(client) {
            if (!client) return;
            pruneClients();
            if (clients.indexOf(client) === -1) {
                clients.push(client);
            }
        }

        function broadcast(message) {
            pruneClients();
            clients.forEach(function (client) {
                post(client, message);
            });
        }

        function updateSnapshot(message) {
            if (!message || typeof message !== 'object') return;

            if (message.type === 'TCN_STATE_SYNC' || message.type === 'TCN_PONG') {
                snapshot = Object.assign({}, snapshot, message, { type: 'TCN_STATE_SYNC' });
                return;
            }

            if (message.type === 'TCN_READY') {
                snapshot = Object.assign({}, snapshot, { state: 'ready', paused: false, callActive: false });
            } else if (message.type === 'TCN_CALL_STARTED') {
                snapshot = Object.assign({}, snapshot, {
                    state: 'calling',
                    phone: message.phone || snapshot.phone || '',
                    callActive: true,
                    callLogId: message.callLogId || snapshot.callLogId || null,
                    minimized: false
                });
            } else if (message.type === 'TCN_CALL_ANSWERED') {
                snapshot = Object.assign({}, snapshot, {
                    state: 'on-call',
                    phone: message.phone || snapshot.phone || '',
                    callActive: true,
                    callLogId: message.callLogId || snapshot.callLogId || null,
                    callEstablishedAt: Date.now()
                });
            } else if (message.type === 'TCN_CALL_ENDED') {
                snapshot = Object.assign({}, snapshot, {
                    state: snapshot.paused ? 'paused' : 'ready',
                    callActive: false,
                    callLogId: null,
                    callEstablishedAt: null
                });
            } else if (message.type === 'TCN_LOGGED_OUT') {
                snapshot = Object.assign({}, snapshot, {
                    state: 'connecting',
                    phone: '',
                    paused: false,
                    muted: false,
                    onHold: false,
                    callActive: false,
                    callLogId: null,
                    callEstablishedAt: null
                });
            } else if (message.type === 'TCN_SIP_DROPPED') {
                snapshot = Object.assign({}, snapshot, {
                    state: snapshot.callActive ? snapshot.state : 'connecting'
                });
            } else if (message.type === 'TCN_ERROR') {
                snapshot = Object.assign({}, snapshot, {
                    state: snapshot.callActive ? snapshot.state : 'error'
                });
            } else if (message.type === 'SP_MINIMIZE') {
                snapshot = Object.assign({}, snapshot, { minimized: true });
            } else if (message.type === 'SP_EXPAND') {
                snapshot = Object.assign({}, snapshot, { minimized: false });
            }
        }

        function sendToChild(message) {
            if (!childLoaded || !childWindow) {
                pending.push(message);
                return;
            }
            post(childWindow, message);
        }

        function flushPending() {
            if (!childLoaded || !childWindow) return;
            pending.splice(0).forEach(function (message) {
                post(childWindow, message);
            });
        }

        frame.addEventListener('load', function () {
            childLoaded = true;
            childWindow = frame.contentWindow;
            flushPending();
            sendToChild({ type: 'PING' });
        });

        window.addEventListener('message', function (event) {
            if (event.origin !== origin) return;

            var message = event.data;
            if (!message || typeof message !== 'object') return;

            if (event.source === frame.contentWindow) {
                childLoaded = true;
                childWindow = frame.contentWindow;
                updateSnapshot(message);
                broadcast(message);
                broadcast(Object.assign({}, snapshot, { type: 'TCN_STATE_SYNC' }));
                return;
            }

            addClient(event.source);

            if (message.type === 'TCN_HOST_CONNECT') {
                post(event.source, { type: 'TCN_HOST_READY', snapshot: Object.assign({}, snapshot) });
                post(event.source, Object.assign({}, snapshot, { type: 'TCN_STATE_SYNC' }));
                sendToChild({ type: 'PING' });
                return;
            }

            if (message.type === 'TCN_HOST_GET_STATE') {
                post(event.source, Object.assign({}, snapshot, { type: 'TCN_STATE_SYNC' }));
                sendToChild({ type: 'PING' });
                return;
            }

            sendToChild(message);
        });
    })();
    </script>
</body>
</html>
