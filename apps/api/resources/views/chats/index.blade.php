@extends('layouts.app')
@section('title', 'Chat Interface')
@section('content')
    <div x-data="chatApp()" class="d-flex flex-grow-1 bg-dark" style="overflow: hidden;">
        <!-- Sidebar Toggle Button (Mobile) -->
        <button @click="sidebarOpen = !sidebarOpen" class="btn btn-dark d-md-none position-fixed"
            style="top: 16px; left: 16px; z-index: 1000; border: 1px solid #444;">
            <i class="bi bi-list"></i>
        </button>

        <!-- Sidebar -->
        <div :class="sidebarOpen ? 'show' : ''" class="sidebar bg-dark border-end"
            style="width: 260px; height: 100%; overflow-y: auto; border-color: #2f2f2f !important; transition: transform 0.3s ease;">

            <button @click="createChat()"
                class="btn btn-outline-light w-100 mb-3 d-flex align-items-center justify-content-center"
                style="margin: 12px 8px; width: calc(100% - 16px) !important; border-radius: 8px; padding: 12px;">
                <i class="bi bi-plus-lg me-2"></i>New chat
            </button>

            <div class="chat-history px-2">
                <template x-for="chat in chats" :key="chat.id">
                    <div @click="selectChat(chat)" :class="currentChat && chat.id === currentChat.id ? 'active' : ''"
                        class="chat-item d-flex align-items-center justify-content-between"
                        style="padding: 12px 16px; margin: 4px 0; border-radius: 8px; cursor: pointer; transition: background 0.2s;">
                        <span x-html="chat.title" class="text-light text-truncate flex-grow-1"
                            style="font-size: 14px;"></span>
                        <i class="bi bi-chat-left-text text-secondary"></i>
                    </div>
                </template>
            </div>
        </div>

        <!-- Main Chat Area -->
        <div class="flex-grow-1 d-flex flex-column" style="background: #212121; height: 100%; overflow: hidden;">

            <!-- Messages Container -->
            <div id="messages" class="flex-grow-1 overflow-auto p-4" style="scroll-behavior: smooth;">

                <!-- Empty State -->
                <div x-show="!currentChat"
                    class="h-100 d-flex flex-column align-items-center justify-content-center text-secondary">
                    <h2 class="text-light mb-4" style="font-size: 32px;">How can I help you today?</h2>
                    <p class="text-secondary">Select a chat or create a new one to get started</p>
                </div>

                <!-- Messages -->
                <div x-show="currentChat" class="messages-wrapper" style="max-width: 900px; margin: 0 auto;">
                    <template x-for="msg in messages" :key="msg.id">
                        <div class="message mb-4" style="animation: slideIn 0.3s ease;">
                            <!-- User Message -->
                            <div x-show="msg.role === 'user'" class="user-message ms-auto">
                                <div class="d-flex align-items-start">
                                    <div class="avatar me-3"
                                        style="width: 50px; height: 30px; background: #e40712; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 13px; color: white; flex-shrink: 0;">
                                        USER
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="text-light" style="line-height: 1.6; font-size: 15px;"
                                            x-html="msg.content">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Assistant Message -->
                            <div x-show="msg.role === 'assistant'" class="assistant-message">
                                <div class="d-flex align-items-start">
                                    <div class="avatar me-3"
                                        style="width: 30px; height: 30px; background: #10a37f; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 13px; color: white; flex-shrink: 0;">
                                        AI
                                    </div>
                                    <div class="flex-grow-1">
                                        <!-- Typing Indicator -->
                                        <template x-if="msg.isTyping">
                                            <div class="typing-dots d-flex align-items-center" style="padding: 16px 20px;">
                                                <span class="dot"></span>
                                                <span class="dot"></span>
                                                <span class="dot"></span>
                                            </div>
                                        </template>

                                        <!-- Content -->
                                        <template x-if="!msg.isTyping">
                                            <div class="text-light mb-2" style="line-height: 1.6; font-size: 15px;"
                                                x-html="msg.content">
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Input Area -->
            <div class="input-container p-4" style="background: #212121; border-top: 1px solid #2f2f2f;">
                <div class="input-wrapper position-relative" style="max-width: 800px; margin: 0 auto;">
                    <form @submit.prevent="sendMessage()" class="position-relative">
                        @csrf
                        <textarea x-model="question" @keydown.enter.prevent="if (!$event.shiftKey) sendMessage()"
                            @input="adjustTextarea($event.target)" placeholder="Message ChatGPT..." rows="1"
                            class="form-control chat-input"
                            style="background: #2f2f2f; border: 1px solid #444; color: #ececec; border-radius: 12px; padding: 14px 50px 14px 16px; resize: none; min-height: 52px; max-height: 200px;"
                            :disabled="!currentChat"></textarea>

                        <button type="submit" :disabled="!question.trim() || !currentChat"
                            class="send-btn position-absolute"
                            style="right: 8px; bottom: 8px; width: 36px; height: 36px; border-radius: 8px; background: #10a37f; border: none; color: white; display: flex; align-items: center; justify-content: center; transition: background 0.2s;">
                            <i class="bi bi-arrow-up"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script>
        function chatApp() {
            return {
                chats: @json($chats),
                currentChat: null,
                messages: [],
                question: '',
                typing: false,
                sidebarOpen: false,

                selectChat(chat) {
                    this.currentChat = chat;
                    this.loadMessages(chat.id);
                    // Close sidebar on mobile after selection
                    if (window.innerWidth < 768) {
                        this.sidebarOpen = false;
                    }
                },

                async loadMessages(chatId) {
                    const res = await fetch(`/chats/${chatId}`);
                    const data = await res.json();
                    this.messages = data.messages.map(m => ({
                        ...m
                    }));
                    this.$nextTick(() => this.scrollBottom());
                },

                async createChat() {
                    const res = await fetch('/chats', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });
                    const newChat = await res.json();
                    this.chats.unshift(newChat);
                    this.selectChat(newChat);
                },

                async sendMessage() {
                    if (!this.question.trim() || !this.currentChat) return;

                    const userMsg = {
                        id: Date.now(),
                        role: 'user',
                        content: this.question
                    };
                    this.messages.push(userMsg);
                    await this.$nextTick();

                    const q = this.question;
                    this.question = '';

                    // Reset textarea height
                    const textarea = document.querySelector('.chat-input');
                    if (textarea) {
                        textarea.style.height = 'auto';
                    }

                    this.typing = true;

                    const assistantMsg = {
                        id: Date.now() + 1,
                        role: 'assistant',
                        content: '',
                        isTyping: true
                    };
                    this.messages.push(assistantMsg);
                    await this.$nextTick();

                    const eventSource = new EventSource(
                        `/chats/${this.currentChat.id}/stream?question=${encodeURIComponent(q)}`);

                    eventSource.onmessage = (event) => {
                        try {
                            const data = JSON.parse(event.data);
                            if (data.delta) {
                                assistantMsg.content += data.delta;
                                const index = this.messages.findIndex(m => m.id === assistantMsg.id);
                                if (index !== -1) this.messages.splice(index, 1, {
                                    ...assistantMsg
                                });
                            }
                            this.$nextTick(() => this.scrollBottom());
                        } catch (e) {
                            console.error(e);
                        }
                    };

                    eventSource.onerror = () => {
                        assistantMsg.isTyping = false;

                        const index = this.messages.findIndex(m => m.id === assistantMsg.id);
                        if (index !== -1) this.messages.splice(index, 1, {
                            ...assistantMsg
                        });
                        this.typing = false;
                        eventSource.close();
                        this.$nextTick(() => this.scrollBottom());
                    };
                },

                scrollBottom() {
                    const container = document.getElementById('messages');
                    if (container) {
                        container.scrollTop = container.scrollHeight;
                    }
                },

                adjustTextarea(textarea) {
                    textarea.style.height = 'auto';
                    textarea.style.height = Math.min(textarea.scrollHeight, 200) + 'px';
                }
            }
        }
    </script>

    <style>
        /* Alpine.js cloak */
        [x-cloak] {
            display: none !important;
        }

        /* Sidebar Styles */
        .sidebar {
            position: relative;
        }

        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: #444;
            border-radius: 3px;
        }

        .chat-item:hover {
            background: #2a2a2a !important;
        }

        .chat-item.active {
            background: #2a2a2a !important;
        }

        /* Messages Scrollbar */
        #messages::-webkit-scrollbar {
            width: 8px;
        }

        #messages::-webkit-scrollbar-thumb {
            background: #444;
            border-radius: 4px;
        }

        /* Animations */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Typing Indicator */
        .typing-dots .dot {
            height: 8px;
            width: 8px;
            background: #8e8e8e;
            border-radius: 50%;
            display: inline-block;
            margin: 0 2px;
            animation: typing 1.4s infinite;
        }

        .typing-dots .dot:nth-child(2) {
            animation-delay: 0.2s;
        }

        .typing-dots .dot:nth-child(3) {
            animation-delay: 0.4s;
        }

        @keyframes typing {

            0%,
            60%,
            100% {
                transform: translateY(0);
                opacity: 0.5;
            }

            30% {
                transform: translateY(-10px);
                opacity: 1;
            }
        }

        /* Input Styles */
        .chat-input:focus {
            background: #353535 !important;
            border-color: #565656 !important;
            box-shadow: none !important;
        }

        .chat-input:disabled {
            background: #1a1a1a !important;
            cursor: not-allowed;
        }

        .send-btn:hover:not(:disabled) {
            background: #0d8c6c !important;
        }

        .send-btn:disabled {
            background: #2f2f2f !important;
            color: #666 !important;
            cursor: not-allowed;
        }

        /* Mobile Sidebar */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                z-index: 999;
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }
        }

        /* Ensure proper spacing */
        .user-message {
            display: inline-block;
        }
    </style>
@endsection
