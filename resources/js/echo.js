import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;
window.Echo   = null;

const cfg = window.__BROADCAST__;

if (cfg && cfg.key && cfg.driver && cfg.driver !== 'null') {
    try {
        if (cfg.driver === 'reverb') {
            window.Echo = new Echo({
                broadcaster:        'reverb',
                key:                cfg.key,
                wsHost:             cfg.reverb.host,
                wsPort:             cfg.reverb.port,
                wssPort:            cfg.reverb.port,
                forceTLS:           cfg.reverb.scheme === 'https',
                enabledTransports:  ['ws', 'wss'],
            });
        } else if (cfg.driver === 'pusher') {
            window.Echo = new Echo({
                broadcaster: 'pusher',
                key:         cfg.key,
                cluster:     cfg.cluster,
                forceTLS:    true,
            });
        }
    } catch (e) {
        console.warn('[Echo] Failed to initialize:', e);
        window.Echo = null;
    }
}
