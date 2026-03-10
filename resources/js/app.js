import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

import { Device } from "@twilio/voice-sdk";
window.TwilioDevice = Device;