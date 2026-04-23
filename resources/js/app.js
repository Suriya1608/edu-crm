import './bootstrap';

import Alpine from 'alpinejs';
import * as Turbo from '@hotwired/turbo';

window.Alpine = Alpine;
window.Turbo  = Turbo;

Alpine.start();

import { Device } from "@twilio/voice-sdk";
window.TwilioDevice = Device;