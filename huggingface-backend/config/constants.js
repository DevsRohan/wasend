'use strict';

module.exports = {
  ENGINE_STATES: {
    BOOT:      'booting',
    QR:        'qr_required',
    AUTH:      'authenticating',
    READY:     'ready',
    DISCO:     'disconnected',
    ERROR:     'error',
  },
  WEBHOOK_EVENTS: {
    INBOUND:        'message_inbound',
    OUTBOUND:       'message_outbound',
    OUTBOUND_ACK:   'message_outbound_ack',
    ENGINE_STATE:   'engine_state',
    LEAD_VALIDATED: 'lead_validated',
    CRON_TICK:      'cron_tick',
  },
  SOCKET_EVENTS: {
    ENGINE_STATUS: 'engine:status',
    ENGINE_QR:     'engine:qr',
    MSG_IN:        'message:inbound',
    MSG_OUT:       'message:outbound',
    MSG_ACK:       'message:ack',
    LEAD_VALID:    'lead:validated',
    CAMPAIGN:      'campaign:state',
    QUEUE_TICK:    'queue:tick',
  },
};
