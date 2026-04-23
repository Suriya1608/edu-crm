@extends('layouts.manager.app')

@section('page_title', 'WhatsApp Chat')

@section('content')
<style>
    /* ── Hub layout ── */
    .wa-hub {
        display: flex;
        height: calc(100vh - 130px);
        background: #fff;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        overflow: hidden;
        box-shadow: 0 2px 16px rgba(19,127,236,.06);
    }

    /* ── Left panel ── */
    .wa-sidebar {
        width: 340px;
        flex-shrink: 0;
        display: flex;
        flex-direction: column;
        border-right: 1px solid #e2e8f0;
        background: #fff;
    }
    .wa-sidebar-header {
        padding: 18px 16px 12px;
        border-bottom: 1px solid #e2e8f0;
    }
    .wa-sidebar-title {
        font-size: 17px;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .wa-sidebar-title .material-icons { color: #25d366; font-size: 22px; }
    .wa-search-box {
        position: relative;
    }
    .wa-search-box .material-icons {
        position: absolute;
        left: 10px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 18px;
        color: #94a3b8;
        pointer-events: none;
    }
    .wa-search-box input {
        width: 100%;
        padding: 8px 12px 8px 36px;
        border: 1.5px solid #e2e8f0;
        border-radius: 10px;
        font-size: 13px;
        font-family: 'Manrope', sans-serif;
        outline: none;
        background: #f6f7f8;
        color: #0f172a;
        transition: border-color .2s;
    }
    .wa-search-box input:focus { border-color: #137fec; background: #fff; }

    .wa-conv-list {
        flex: 1;
        overflow-y: auto;
    }
    .wa-conv-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        cursor: pointer;
        border-bottom: 1px solid #f1f5f9;
        transition: background .15s;
        text-decoration: none;
        color: inherit;
    }
    .wa-conv-item:hover { background: #f6f7f8; }
    .wa-conv-item.active { background: #e8f3fd; border-left: 3px solid #137fec; }
    .wa-conv-avatar {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: linear-gradient(135deg, #137fec, #0a58a8);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 16px;
        flex-shrink: 0;
    }
    .wa-conv-body { flex: 1; min-width: 0; }
    .wa-conv-name {
        font-size: 14px;
        font-weight: 600;
        color: #0f172a;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .wa-conv-preview {
        font-size: 12px;
        color: #64748b;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-top: 2px;
    }
    .wa-conv-preview .direction-icon { font-size: 12px; vertical-align: middle; }
    .wa-conv-meta {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 4px;
        flex-shrink: 0;
    }
    .wa-conv-time { font-size: 11px; color: #94a3b8; }
    .wa-unread-badge {
        background: #25d366;
        color: #fff;
        border-radius: 999px;
        font-size: 10px;
        font-weight: 700;
        min-width: 18px;
        height: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0 5px;
    }
    .wa-empty-conv {
        padding: 48px 24px;
        text-align: center;
        color: #94a3b8;
        font-size: 13px;
    }
    .wa-empty-conv .material-icons { font-size: 48px; display: block; margin-bottom: 12px; color: #e2e8f0; }

    /* ── Right panel ── */
    .wa-main {
        flex: 1;
        display: flex;
        flex-direction: column;
        background: #f0f2f5;
        min-width: 0;
    }
    .wa-main-empty {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: #94a3b8;
        gap: 12px;
    }
    .wa-main-empty .material-icons { font-size: 64px; color: #e2e8f0; }
    .wa-main-empty p { font-size: 14px; }

    /* Chat header */
    .wa-chat-head {
        background: #fff;
        border-bottom: 1px solid #e2e8f0;
        padding: 12px 20px;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .wa-chat-head-avatar {
        width: 40px; height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, #137fec, #0a58a8);
        color: #fff;
        display: flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: 15px;
        flex-shrink: 0;
    }
    .wa-chat-head-info { flex: 1; }
    .wa-chat-head-name { font-size: 15px; font-weight: 700; color: #0f172a; }
    .wa-chat-head-phone { font-size: 12px; color: #64748b; }
    .wa-chat-head-actions { display: flex; gap: 8px; }

    /* Messages area */
    .wa-messages-area {
        flex: 1;
        overflow-y: auto;
        padding: 20px;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .wa-day-divider {
        text-align: center;
        font-size: 11px;
        color: #94a3b8;
        background: #dde2e9;
        border-radius: 999px;
        padding: 3px 12px;
        align-self: center;
        margin: 6px 0;
    }
    .wa-bubble {
        max-width: 65%;
        padding: 9px 12px 6px;
        border-radius: 12px;
        font-size: 14px;
        line-height: 1.4;
        position: relative;
        word-break: break-word;
    }
    .wa-bubble.inbound {
        background: #fff;
        align-self: flex-start;
        border-bottom-left-radius: 4px;
        box-shadow: 0 1px 2px rgba(0,0,0,.08);
    }
    .wa-bubble.outbound {
        background: #dcf8c6;
        align-self: flex-end;
        border-bottom-right-radius: 4px;
        box-shadow: 0 1px 2px rgba(0,0,0,.08);
    }
    .wa-bubble-text { color: #0f172a; }
    .wa-bubble-meta {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 4px;
        margin-top: 4px;
    }
    .wa-bubble-time { font-size: 10px; color: #94a3b8; }
    .wa-tick { font-size: 14px; color: #94a3b8; }
    .wa-tick.sent { color: #94a3b8; }
    .wa-tick.delivered { color: #94a3b8; }
    .wa-tick.read { color: #53bdeb; }

    /* Media bubbles */
    .wa-media-img {
        max-width: 240px;
        max-height: 200px;
        border-radius: 8px;
        display: block;
        margin-bottom: 4px;
        cursor: pointer;
        object-fit: cover;
    }
    .wa-media-doc {
        display: flex;
        align-items: center;
        gap: 8px;
        background: rgba(0,0,0,.06);
        border-radius: 8px;
        padding: 8px 10px;
        margin-bottom: 4px;
        text-decoration: none;
        color: inherit;
        font-size: 13px;
        font-weight: 600;
    }
    .wa-media-doc .material-icons { font-size: 22px; color: #137fec; flex-shrink: 0; }
    .wa-media-doc:hover { background: rgba(0,0,0,.1); }
    .wa-media-audio { width: 100%; min-width: 200px; margin-bottom: 4px; }

    /* File preview area */
    .wa-file-preview {
        display: flex;
        align-items: center;
        gap: 10px;
        background: #f0f9ff;
        border: 1.5px solid #bae6fd;
        border-radius: 10px;
        padding: 8px 12px;
        margin-bottom: 8px;
        font-size: 13px;
    }
    .wa-file-preview .material-icons { color: #137fec; }
    .wa-file-preview-name { flex: 1; font-weight: 600; color: #0f172a; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .wa-file-preview-size { color: #64748b; font-size: 11px; white-space: nowrap; }
    .wa-file-remove { background: none; border: none; cursor: pointer; padding: 0; color: #ef4444; display: flex; }

    /* Attach button */
    .wa-attach-btn {
        width: 44px; height: 44px;
        border-radius: 50%;
        background: #f1f5f9;
        border: 1.5px solid #e2e8f0;
        color: #64748b;
        display: flex; align-items: center; justify-content: center;
        cursor: pointer;
        transition: background .15s;
        flex-shrink: 0;
    }
    .wa-attach-btn:hover { background: #e2e8f0; }
    .wa-attach-btn .material-icons { font-size: 20px; }

    /* Composer */
    .wa-composer {
        background: #fff;
        border-top: 1px solid #e2e8f0;
        padding: 12px 16px;
    }
    .wa-template-row {
        display: flex;
        gap: 8px;
        overflow-x: auto;
        margin-bottom: 10px;
        padding-bottom: 2px;
    }
    .wa-template-row::-webkit-scrollbar { height: 3px; }
    .wa-tpl-btn {
        border: 1.5px solid #d5ddeb;
        background: #f8fafc;
        border-radius: 999px;
        font-size: 12px;
        font-family: 'Manrope', sans-serif;
        white-space: nowrap;
        padding: 5px 12px;
        cursor: pointer;
        transition: all .15s;
        color: #0f172a;
        flex-shrink: 0;
    }
    .wa-tpl-btn:hover { background: #137fec; color: #fff; border-color: #137fec; }
    .wa-input-row {
        display: flex;
        gap: 10px;
        align-items: flex-end;
    }
    .wa-input-row textarea {
        flex: 1;
        border: 1.5px solid #e2e8f0;
        border-radius: 12px;
        padding: 10px 14px;
        font-size: 14px;
        font-family: 'Manrope', sans-serif;
        resize: none;
        outline: none;
        max-height: 120px;
        transition: border-color .2s;
        color: #0f172a;
    }
    .wa-input-row textarea:focus { border-color: #137fec; }
    .wa-send-btn {
        width: 44px; height: 44px;
        border-radius: 50%;
        background: #25d366;
        border: none;
        color: #fff;
        display: flex; align-items: center; justify-content: center;
        cursor: pointer;
        transition: background .15s, transform .1s;
        flex-shrink: 0;
    }
    .wa-send-btn:hover { background: #1aab56; }
    .wa-send-btn:active { transform: scale(.94); }
    .wa-send-btn .material-icons { font-size: 20px; }
    .wa-send-btn:disabled { background: #d1fae5; cursor: not-allowed; }

    /* Sending spinner */
    .wa-spinner {
        display: none;
        width: 16px; height: 16px;
        border: 2px solid rgba(255,255,255,.4);
        border-top-color: #fff;
        border-radius: 50%;
        animation: wa-spin .7s linear infinite;
    }
    @keyframes wa-spin { to { transform: rotate(360deg); } }

    /* Error toast */
    .wa-toast {
        position: fixed;
        bottom: 24px; left: 50%;
        transform: translateX(-50%);
        background: #ef4444;
        color: #fff;
        padding: 10px 20px;
        border-radius: 10px;
        font-size: 13px;
        z-index: 9999;
        display: none;
        box-shadow: 0 4px 16px rgba(0,0,0,.2);
    }

    /* Responsive */
    @media (max-width: 768px) {
        .wa-hub { height: calc(100vh - 100px); border-radius: 0; }
        .wa-sidebar { width: 100%; border-right: none; }
        .wa-sidebar.mobile-hidden { display: none; }
        .wa-main.mobile-hidden { display: none; }
        .wa-back-btn { display: flex !important; }
    }
</style>

<div class="dashboard-content" style="padding-top: 0;">
    <div class="wa-hub" id="waHub">

        {{-- ══════════════ LEFT: Conversations ══════════════ --}}
        <div class="wa-sidebar" id="waSidebar">
            <div class="wa-sidebar-header">
                <div class="wa-sidebar-title">
                    <span class="material-icons">chat</span>
                    WhatsApp Chat
                </div>
                <div class="wa-search-box">
                    <span class="material-icons">search</span>
                    <input type="text" id="convSearch" placeholder="Search leads…" autocomplete="off">
                </div>
            </div>

            <div class="wa-conv-list" id="convList">
                @forelse ($conversations as $conv)
                    @php
                        $lastMsg  = $conv->whatsappMessages->first();
                        $unread   = $unreadCounts[$conv->id] ?? 0;
                        $isActive = $activeLead && $activeLead->id === $conv->id;
                    @endphp
                    <a href="#"
                       class="wa-conv-item {{ $isActive ? 'active' : '' }}"
                       data-lead-id="{{ $conv->id }}"
                       data-encrypted="{{ encrypt($conv->id) }}"
                       data-name="{{ $conv->name }}"
                       data-phone="{{ $conv->phone }}"
                       data-lead-url="{{ route('manager.leads.show', encrypt($conv->id)) }}">
                        <div class="wa-conv-avatar">{{ strtoupper(substr($conv->name, 0, 1)) }}</div>
                        <div class="wa-conv-body">
                            <div class="wa-conv-name">{{ $conv->name }}</div>
                            <div class="wa-conv-preview">
                                @if ($lastMsg)
                                    @if ($lastMsg->direction === 'outbound')
                                        <span class="material-icons direction-icon">done_all</span>
                                    @endif
                                    {{ Str::limit($lastMsg->message_body, 38) }}
                                @else
                                    <em>No messages</em>
                                @endif
                            </div>
                        </div>
                        <div class="wa-conv-meta">
                            <span class="wa-conv-time">{{ $lastMsg?->created_at?->format('h:i A') }}</span>
                            @if ($unread > 0)
                                <span class="wa-unread-badge" data-unread="{{ $conv->id }}">{{ $unread }}</span>
                            @else
                                <span class="wa-unread-badge" data-unread="{{ $conv->id }}" style="display:none;">0</span>
                            @endif
                        </div>
                    </a>
                @empty
                    <div class="wa-empty-conv">
                        <span class="material-icons">chat_bubble_outline</span>
                        No WhatsApp conversations yet.
                    </div>
                @endforelse
            </div>
        </div>

        {{-- ══════════════ RIGHT: Chat Window ══════════════ --}}
        <div class="wa-main" id="waMain">

            {{-- Empty state --}}
            <div class="wa-main-empty" id="waEmptyState" style="{{ $activeLead ? 'display:none;' : '' }}">
                <span class="material-icons">forum</span>
                <p>Select a conversation to start chatting</p>
            </div>

            {{-- Active chat --}}
            <div id="waChatPanel" style="{{ $activeLead ? '' : 'display:none;' }} display:flex; flex-direction:column; height:100%;">

                {{-- Header --}}
                <div class="wa-chat-head" id="waChatHead">
                    <button class="btn btn-sm btn-light wa-back-btn" id="waBackBtn" style="display:none;">
                        <span class="material-icons" style="font-size:18px;">arrow_back</span>
                    </button>
                    <div class="wa-chat-head-avatar" id="headAvatar">
                        {{ $activeLead ? strtoupper(substr($activeLead->name, 0, 1)) : '' }}
                    </div>
                    <div class="wa-chat-head-info">
                        <div class="wa-chat-head-name" id="headName">{{ $activeLead?->name ?? '' }}</div>
                        <div class="wa-chat-head-phone" id="headPhone">{{ $activeLead?->phone ?? '' }}</div>
                    </div>
                    <div class="wa-chat-head-actions">
                        <a href="#" id="headLeadLink" class="btn btn-sm btn-outline-primary" target="_blank"
                           {{ $activeLead ? 'href="'.route('manager.leads.show', encrypt($activeLead->id)).'"' : '' }}>
                            <span class="material-icons" style="font-size:16px;">open_in_new</span>
                            Lead Profile
                        </a>
                    </div>
                </div>

                {{-- Messages --}}
                <div class="wa-messages-area" id="waMsgArea">
                    @if ($activeLead)
                        @php $lastDate = null; @endphp
                        @foreach ($activeMessages as $msg)
                            @php $msgDate = $msg->created_at?->format('d M Y'); @endphp
                            @if ($msgDate !== $lastDate)
                                <div class="wa-day-divider">{{ $msgDate }}</div>
                                @php $lastDate = $msgDate; @endphp
                            @endif
                            <div class="wa-bubble {{ $msg->direction }}" data-msg-id="{{ $msg->id }}">
                                @if ($msg->media_type && $msg->media_url)
                                    @php $mediaPublicUrl = asset('storage/' . $msg->media_url); @endphp
                                    @if ($msg->media_type === 'image')
                                        <img src="{{ $mediaPublicUrl }}" class="wa-media-img"
                                             onclick="window.open(this.src,'_blank')" alt="Image">
                                    @elseif ($msg->media_type === 'audio')
                                        <audio controls class="wa-media-audio">
                                            <source src="{{ $mediaPublicUrl }}">
                                        </audio>
                                    @elseif ($msg->media_type === 'video')
                                        <video controls class="wa-media-img" style="max-height:200px;">
                                            <source src="{{ $mediaPublicUrl }}">
                                        </video>
                                    @else
                                        <a href="{{ $mediaPublicUrl }}" target="_blank" class="wa-media-doc" download>
                                            <span class="material-icons">description</span>
                                            {{ $msg->media_filename ?? basename($msg->media_url) }}
                                        </a>
                                    @endif
                                @endif
                                @if ($msg->message_body && !($msg->media_type && in_array($msg->media_type, ['image','audio','video'])))
                                    <div class="wa-bubble-text">{{ $msg->message_body }}</div>
                                @endif
                                <div class="wa-bubble-meta">
                                    <span class="wa-bubble-time">{{ $msg->created_at?->format('h:i A') }}</span>
                                    @if ($msg->direction === 'outbound')
                                        @php $status = data_get($msg->meta_data, 'meta_status', 'sent'); @endphp
                                        <span class="material-icons wa-tick {{ $status }}">done_all</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>

                {{-- Composer --}}
                <div class="wa-composer" id="waComposer">
                    <div class="wa-template-row" id="waTplRow">
                        {{-- Templates filled by JS when lead is selected --}}
                    </div>
                    {{-- File preview --}}
                    <div class="wa-file-preview" id="waFilePreview" style="display:none;">
                        <span class="material-icons" id="filePreviewIcon">attach_file</span>
                        <span class="wa-file-preview-name" id="filePreviewName"></span>
                        <span class="wa-file-preview-size" id="filePreviewSize"></span>
                        <button type="button" class="wa-file-remove" id="fileRemoveBtn" title="Remove">
                            <span class="material-icons" style="font-size:18px;">close</span>
                        </button>
                    </div>
                    <form id="waSendForm" class="wa-input-row">
                        @csrf
                        <input type="file" id="waFileInput" style="display:none;"
                               accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip">
                        <button type="button" class="wa-attach-btn" id="waAttachBtn" title="Attach file">
                            <span class="material-icons">attach_file</span>
                        </button>
                        <textarea id="waMsgInput" rows="1" placeholder="Type a message…" autocomplete="off"></textarea>
                        <button type="submit" class="wa-send-btn" id="waSendBtn">
                            <span class="material-icons" id="sendIcon">send</span>
                            <div class="wa-spinner" id="sendSpinner"></div>
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<div class="wa-toast" id="waToast"></div>

<script>
(function () {
    /* ── State ── */
    const state = {
        leadId: @json($activeLead?->id),
        encryptedId: @json($activeLead ? encrypt($activeLead->id) : null),
        leadName: @json($activeLead?->name ?? ''),
        leadPhone: @json($activeLead?->phone ?? ''),
        leadUrl: @json($activeLead ? route('manager.leads.show', encrypt($activeLead->id)) : ''),
        lastMsgId: 0,
        pollTimer: null,
    };

    /* ── DOM refs ── */
    const convList    = document.getElementById('convList');
    const emptyState  = document.getElementById('waEmptyState');
    const chatPanel   = document.getElementById('waChatPanel');
    const msgArea     = document.getElementById('waMsgArea');
    const sendForm    = document.getElementById('waSendForm');
    const msgInput    = document.getElementById('waMsgInput');
    const sendBtn     = document.getElementById('waSendBtn');
    const sendIcon    = document.getElementById('sendIcon');
    const sendSpinner = document.getElementById('sendSpinner');
    const headAvatar  = document.getElementById('headAvatar');
    const headName    = document.getElementById('headName');
    const headPhone   = document.getElementById('headPhone');
    const headLink    = document.getElementById('headLeadLink');
    const tplRow      = document.getElementById('waTplRow');
    const toast       = document.getElementById('waToast');
    const backBtn     = document.getElementById('waBackBtn');
    const sidebar     = document.getElementById('waSidebar');
    const waMain      = document.getElementById('waMain');
    const searchInput = document.getElementById('convSearch');

    const SEND_URL_PATTERN  = @json(route('manager.leads.whatsapp.store', '__ID__'));
    const MEDIA_URL_PATTERN = @json(route('manager.leads.whatsapp.media', '__ID__'));
    const MSG_URL_PATTERN   = @json(route('manager.whatsapp.messages', '__ID__'));
    const CSRF              = document.querySelector('meta[name="csrf-token"]')?.content || '';

    /* ── Media refs ── */
    const fileInput     = document.getElementById('waFileInput');
    const attachBtn     = document.getElementById('waAttachBtn');
    const filePreview   = document.getElementById('waFilePreview');
    const filePreviewName = document.getElementById('filePreviewName');
    const filePreviewSize = document.getElementById('filePreviewSize');
    const filePreviewIcon = document.getElementById('filePreviewIcon');
    const fileRemoveBtn = document.getElementById('fileRemoveBtn');

    let pendingFile = null;

    const TEMPLATES = [
        { label: 'Intro',       msg: (name) => `Hello ${name}, thanks for your interest. Can we connect now?` },
        { label: 'Follow-up',   msg: () => `Reminder: your follow-up is scheduled. Please confirm your preferred time.` },
        { label: 'Course Info', msg: () => `Please share your preferred course and we'll guide you with next steps.` },
        { label: 'Admission',   msg: (name) => `Hi ${name}, the admission process is now open. Let's get you enrolled!` },
    ];

    /* ── Init: scroll to bottom on page load ── */
    if (state.leadId) {
        scrollToBottom(false);
        state.lastMsgId = lastRenderedMsgId();
        startPolling();
        buildTemplates(state.leadName);
    }

    /* ── Conversation click ── */
    convList.addEventListener('click', function (e) {
        const item = e.target.closest('.wa-conv-item');
        if (!item) return;
        e.preventDefault();
        openConversation(item);
    });

    function openConversation(item) {
        const leadId      = item.dataset.leadId;
        const encryptedId = item.dataset.encrypted;
        const name        = item.dataset.name;
        const phone       = item.dataset.phone;
        const leadUrl     = item.dataset.leadUrl;

        // Highlight active
        document.querySelectorAll('.wa-conv-item').forEach(i => i.classList.remove('active'));
        item.classList.add('active');

        // Update state
        state.leadId      = leadId;
        state.encryptedId = encryptedId;
        state.leadName    = name;
        state.leadPhone   = phone;
        state.leadUrl     = leadUrl;
        state.lastMsgId   = 0;

        // Update header
        headAvatar.textContent = name.charAt(0).toUpperCase();
        headName.textContent   = name;
        headPhone.textContent  = phone;
        headLink.href          = leadUrl;

        // Clear & show chat
        msgArea.innerHTML = '';
        emptyState.style.display = 'none';
        chatPanel.style.display  = 'flex';
        chatPanel.style.flexDirection = 'column';
        chatPanel.style.height = '100%';

        // Mobile
        if (window.innerWidth <= 768) {
            sidebar.classList.add('mobile-hidden');
            backBtn.style.display = 'flex';
        }

        // Templates
        buildTemplates(name);

        // Load messages
        fetchMessages();
        stopPolling();
        startPolling();
        msgInput.focus();
    }

    /* ── Templates ── */
    function buildTemplates(name) {
        tplRow.innerHTML = '';
        TEMPLATES.forEach(t => {
            const btn = document.createElement('button');
            btn.type      = 'button';
            btn.className = 'wa-tpl-btn';
            btn.textContent = t.label;
            btn.addEventListener('click', () => {
                msgInput.value = t.msg(name);
                msgInput.focus();
                autoResize();
            });
            tplRow.appendChild(btn);
        });
    }

    /* ── Attach button ── */
    attachBtn.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;
        pendingFile = file;
        filePreviewName.textContent = file.name;
        filePreviewSize.textContent = formatBytes(file.size);
        filePreviewIcon.textContent = file.type.startsWith('image/') ? 'image' :
                                      file.type.startsWith('video/') ? 'videocam' :
                                      file.type.startsWith('audio/') ? 'headphones' : 'description';
        filePreview.style.display = 'flex';
        msgInput.placeholder = 'Add a caption (optional)…';
    });
    fileRemoveBtn.addEventListener('click', clearFile);

    function clearFile() {
        pendingFile = null;
        fileInput.value = '';
        filePreview.style.display = 'none';
        msgInput.placeholder = 'Type a message…';
    }

    function formatBytes(b) {
        if (b < 1024) return b + ' B';
        if (b < 1048576) return (b / 1024).toFixed(1) + ' KB';
        return (b / 1048576).toFixed(1) + ' MB';
    }

    /* ── Send message (text or media) ── */
    sendForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (!state.leadId) return;

        if (pendingFile) {
            await sendMediaFile();
        } else {
            await sendTextMessage();
        }
    });

    async function sendTextMessage() {
        const text = msgInput.value.trim();
        if (!text) return;

        setSending(true);
        try {
            const url = SEND_URL_PATTERN.replace('__ID__', state.encryptedId);
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ message: text }),
            });

            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'Send failed');

            msgInput.value = '';
            autoResize();
            appendBubble({
                id: data.message_id,
                body: data.message || text,
                direction: 'outbound',
                time: data.time || now(),
                status: 'sent',
            });
            state.lastMsgId = data.message_id || state.lastMsgId;
            updateConvPreview(state.leadId, text, 'outbound');
        } catch (err) {
            showToast(err.message || 'Failed to send message.');
        } finally {
            setSending(false);
        }
    }

    async function sendMediaFile() {
        if (!pendingFile) return;
        setSending(true);
        try {
            const url = MEDIA_URL_PATTERN.replace('__ID__', state.encryptedId);
            const fd  = new FormData();
            fd.append('_token', CSRF);
            fd.append('file', pendingFile);
            const caption = msgInput.value.trim();
            if (caption) fd.append('caption', caption);

            const res  = await fetch(url, { method: 'POST', headers: { 'Accept': 'application/json' }, body: fd });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'Upload failed');

            clearFile();
            msgInput.value = '';
            autoResize();
            appendBubble({
                id:             data.message_id,
                body:           data.message,
                direction:      'outbound',
                time:           data.time || now(),
                status:         'sent',
                media_type:     data.media_type,
                media_url:      data.media_url,
                media_filename: data.media_filename,
            });
            state.lastMsgId = data.message_id || state.lastMsgId;
            updateConvPreview(state.leadId, data.message, 'outbound');
        } catch (err) {
            showToast(err.message || 'Failed to send file.');
        } finally {
            setSending(false);
        }
    }

    /* ── Auto-resize textarea ── */
    msgInput.addEventListener('input', autoResize);
    function autoResize() {
        msgInput.style.height = 'auto';
        msgInput.style.height = Math.min(msgInput.scrollHeight, 120) + 'px';
    }

    /* ── Enter to send (Shift+Enter for newline) ── */
    msgInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendForm.dispatchEvent(new Event('submit'));
        }
    });

    /* ── Back button (mobile) ── */
    backBtn.addEventListener('click', function () {
        sidebar.classList.remove('mobile-hidden');
        backBtn.style.display = 'none';
        chatPanel.style.display  = 'none';
        emptyState.style.display = '';
        stopPolling();
    });

    /* ── Polling ── */
    function startPolling() {
        stopPolling();
        state.pollTimer = setInterval(fetchMessages, 7000);
    }
    function stopPolling() {
        if (state.pollTimer) { clearInterval(state.pollTimer); state.pollTimer = null; }
    }

    async function fetchMessages() {
        if (!state.encryptedId) return;
        try {
            const url = MSG_URL_PATTERN.replace('__ID__', state.encryptedId)
                      + '?after=' + state.lastMsgId;
            const res  = await fetch(url, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();

            if (data.messages && data.messages.length > 0) {
                data.messages.forEach(m => {
                    if (m.id > state.lastMsgId) {
                        appendBubble(m);
                        state.lastMsgId = m.id;
                        updateConvPreview(state.leadId, m.body || m.message_body || '', m.direction);
                    }
                });
            }

            // Update tick status for existing outbound bubbles
            if (data.statuses) {
                Object.entries(data.statuses).forEach(([id, status]) => {
                    const bubble = msgArea.querySelector(`[data-msg-id="${id}"]`);
                    if (bubble) {
                        const tick = bubble.querySelector('.wa-tick');
                        if (tick) { tick.className = `material-icons wa-tick ${status}`; }
                    }
                });
            }

            // Update unread badges from server
            if (data.unread) {
                document.querySelectorAll('[data-unread]').forEach(el => {
                    const lid = el.dataset.unread;
                    const cnt = data.unread[lid] || 0;
                    el.textContent = cnt;
                    el.style.display = cnt > 0 ? '' : 'none';
                });
            }
        } catch (_) {}
    }

    /* ── Append a bubble ── */
    let lastRenderedDate = null;

    function appendBubble(msg) {
        const dateStr = msg.date || new Date().toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' });
        if (dateStr !== lastRenderedDate) {
            const div = document.createElement('div');
            div.className = 'wa-day-divider';
            div.textContent = dateStr;
            msgArea.appendChild(div);
            lastRenderedDate = dateStr;
        }

        const wrapper = document.createElement('div');
        wrapper.className = `wa-bubble ${msg.direction}`;
        wrapper.dataset.msgId = msg.id;

        const tick = msg.direction === 'outbound'
            ? `<span class="material-icons wa-tick ${msg.status || 'sent'}">done_all</span>`
            : '';

        const bodyText = msg.body || msg.message_body || '';
        let mediaHtml  = '';

        if (msg.media_type && msg.media_url) {
            if (msg.media_type === 'image') {
                mediaHtml = `<img src="${escHtml(msg.media_url)}" class="wa-media-img" onclick="window.open(this.src,'_blank')" alt="Image">`;
            } else if (msg.media_type === 'audio') {
                mediaHtml = `<audio controls class="wa-media-audio"><source src="${escHtml(msg.media_url)}"></audio>`;
            } else if (msg.media_type === 'video') {
                mediaHtml = `<video controls class="wa-media-img" style="max-height:200px;"><source src="${escHtml(msg.media_url)}"></video>`;
            } else {
                const fname = escHtml(msg.media_filename || 'File');
                mediaHtml = `<a href="${escHtml(msg.media_url)}" target="_blank" class="wa-media-doc" download>
                    <span class="material-icons">description</span>${fname}</a>`;
            }
        }

        const showText = bodyText && !['image','audio','video'].includes(msg.media_type || '');

        wrapper.innerHTML = `
            ${mediaHtml}
            ${showText ? `<div class="wa-bubble-text">${escHtml(bodyText)}</div>` : ''}
            <div class="wa-bubble-meta">
                <span class="wa-bubble-time">${msg.time || now()}</span>
                ${tick}
            </div>`;
        msgArea.appendChild(wrapper);
        scrollToBottom();
    }

    /* ── Update conversation list preview ── */
    function updateConvPreview(leadId, text, direction) {
        const item = convList.querySelector(`[data-lead-id="${leadId}"]`);
        if (!item) return;
        const preview = item.querySelector('.wa-conv-preview');
        if (preview) {
            const icon = direction === 'outbound'
                ? '<span class="material-icons direction-icon">done_all</span> '
                : '';
            preview.innerHTML = icon + escHtml(text.substring(0, 38)) + (text.length > 38 ? '…' : '');
        }
        const meta = item.querySelector('.wa-conv-time');
        if (meta) meta.textContent = now();
        // Move to top
        convList.prepend(item);
    }

    /* ── Conversation search ── */
    searchInput.addEventListener('input', function () {
        const q = this.value.toLowerCase();
        convList.querySelectorAll('.wa-conv-item').forEach(item => {
            const name = (item.dataset.name || '').toLowerCase();
            const phone = (item.dataset.phone || '').toLowerCase();
            item.style.display = (name.includes(q) || phone.includes(q)) ? '' : 'none';
        });
    });

    /* ── Helpers ── */
    function scrollToBottom(smooth = true) {
        msgArea.scrollTo({ top: msgArea.scrollHeight, behavior: smooth ? 'smooth' : 'instant' });
    }

    function lastRenderedMsgId() {
        const bubbles = msgArea.querySelectorAll('[data-msg-id]');
        if (!bubbles.length) return 0;
        return parseInt(bubbles[bubbles.length - 1].dataset.msgId) || 0;
    }

    function setSending(v) {
        sendBtn.disabled = v;
        sendIcon.style.display  = v ? 'none' : '';
        sendSpinner.style.display = v ? 'block' : 'none';
    }

    function showToast(msg) {
        toast.textContent = msg;
        toast.style.display = 'block';
        setTimeout(() => { toast.style.display = 'none'; }, 3500);
    }

    function now() {
        return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // Init lastRenderedDate from already-rendered bubbles
    const firstDivider = msgArea.querySelector('.wa-day-divider');
    if (firstDivider) {
        const allDividers = msgArea.querySelectorAll('.wa-day-divider');
        lastRenderedDate = allDividers[allDividers.length - 1]?.textContent || null;
    }
    state.lastMsgId = lastRenderedMsgId();
})();
</script>
@endsection
