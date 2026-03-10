<style>
        .wa-chat-window {
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid #e7eaf0;
        }

        .wa-chat-header {
            background: linear-gradient(135deg, #0f9d58, #128c7e);
            color: #fff;
            padding: 14px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .wa-user-block {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .wa-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.2);
            font-weight: 700;
        }

        .wa-live-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #9cffc6;
            box-shadow: 0 0 0 4px rgba(156, 255, 198, 0.25);
        }

        .wa-chat-body {
            background: #f0f2f5;
            min-height: 280px;
            max-height: 380px;
            overflow-y: auto;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .wa-message {
            max-width: 78%;
            padding: 10px 12px;
            border-radius: 10px;
            line-height: 1.3;
            font-size: 14px;
        }

        .wa-message small {
            color: #667085;
            font-size: 11px;
        }

        .wa-incoming {
            background: #fff;
            align-self: flex-start;
        }

        .wa-outgoing {
            background: #dcf8c6;
            align-self: flex-end;
        }

        .wa-chat-footer {
            padding: 12px;
            border-top: 1px solid #eef1f6;
            background: #fff;
        }

        .wa-template-row {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            margin-bottom: 10px;
        }

        .wa-template-btn {
            border: 1px solid #d5ddeb;
            background: #f8fafc;
            border-radius: 999px;
            font-size: 12px;
            white-space: nowrap;
            padding: 6px 10px;
        }

        .wa-composer-form {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .wa-composer-form .form-control {
            border-radius: 10px;
            min-height: 42px;
        }

        .wa-composer-form .btn {
            width: 44px;
            height: 42px;
            padding: 0;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Message meta row (time + tick) */
        .wa-message-meta {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 3px;
            margin-top: 2px;
        }

        .wa-message-meta small {
            color: #667085;
            font-size: 11px;
        }

        /* Delivery tick marks */
        .wa-tick {
            font-size: 12px;
            font-weight: 700;
            letter-spacing: -2px;
            display: inline-block;
            padding-right: 1px;
        }

        .wa-tick-sent     { color: #9e9e9e; }
        .wa-tick-delivered{ color: #9e9e9e; }
        .wa-tick-read     { color: #4fc3f7; }
    </style>
