<!DOCTYPE html>
<html>
<head>
    <title>Chat</title>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <style>
        #chat-window { border: 1px solid #ccc; height: 400px; overflow-y: scroll; }
        #messages .message { margin: 5px; padding: 5px; }
        .sent { text-align: right; background: #dcf8c6; }
        .received { text-align: left; background: #fff; }
    </style>
</head>
<body>
    <h1>Chat</h1>
    <form method="POST" action="/logout">
        @csrf
        <button type="submit">Logout</button>
    </form>
    <div id="search">
        <input id="search-input" placeholder="Search users...">
        <ul id="user-list"></ul>
    </div>
    <input type="hidden" name="" id="message_csrf_token" value="{{ csrf_token() }}">
    <form id="sendMessageForm">
        <div id="messages"></div>
        @csrf
        <input type="hidden" value="" name="to_user_id" id="sendUserId">
        <input id="message-input" name="content" placeholder="Type message...">
        <button id="send-btn">Send</button>
        <input type="file" id="file-input" accept="image/*,video/*">
    </form>
    <script>
        let currentChatUser = null;
        let ws;
        const authUserId = {{ auth()->id() }};

        function appendMessage(msg) {
            let html;
            if (msg.type === 'text') {
                html = `<p>${msg.content}</p>`;
            } else if (msg.type === 'image') {
                html = `<img src="/storage/${msg.content}" width="200">`;
            } else if (msg.type === 'video') {
                html = `<video src="/storage/${msg.content}" width="200" controls></video>`;
            }

            const className = (msg.from_user_id === authUserId) ? 'sent' : 'received';
            $('#messages').append(`<div class="message ${className}">${html}</div>`);

            const chatWindow = document.getElementById('chat-window');
            if (chatWindow) {
                chatWindow.scrollTop = chatWindow.scrollHeight;
            }
        }

        // small HTML escaper
        function escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;");
        }

        function connectWebSocket() {
            // Use host where ws_server.php is running
            const wsHost = window.location.hostname; // or explicit host
            ws = new WebSocket('ws://' + wsHost + ':8080');

            ws.onopen = function() {
                console.log('ws connected');
            };

            ws.onmessage = function(evt) {
                try {
                    const data = JSON.parse(evt.data);
                    if (data.action === 'new_message') {
                        const msg = data.message;
                        // If this message is for the current chat (either to or from currentChatUser)
                        if (!currentChatUser) {
                            // not viewing a chat - ignore or show notification
                            console.log('incoming msg (not viewing):', msg);
                        } else {
                            // check if message belongs to current chat
                            if ((msg.from_user_id == currentChatUser && msg.to_user_id == authUserId) ||
                                (msg.to_user_id == currentChatUser && msg.from_user_id == authUserId)) {
                                appendMessage(msg);
                            } else {
                                // message for other chat - you might want to show unread indicator
                            }
                        }
                    }
                } catch (e) {
                    console.error('Invalid ws payload', e);
                }
            };

            ws.onclose = function() {
                console.log('ws closed, retrying in 2s...');
                setTimeout(connectWebSocket, 2000);
            };

            ws.onerror = function(err) {
                console.error('ws error', err);
                ws.close();
            };
        }

        // Call once on page load
        $(document).ready(function() {
            connectWebSocket();

            // example: when user clicks a contact to open chat
            $(document).on('click', '.user-line', function() {
                const userId = $(this).data('userid');
                loadChat(userId);
            });

            // message send form
            $('#sendMessageForm').on('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);

                $.ajax({
                    url: '/messages',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(res) {
                        // do not append here. Wait for ws push (dedup prevents duplicates).
                        $('#message_input').val('');
                    },
                    error: function(err) {
                        console.error(err);
                    }
                });
            });
        });

        function loadChat(userId) {
            $('#sendUserId').val(userId);
            currentChatUser = userId;
            $('#messages').empty();
            // load full history once (you can paginate as needed)
            $.get('/messages/' + userId, function(data) {
                data.forEach(msg => appendMessage(msg));
            });
        }
    </script>

    <script>
        $(document).ready(function() {
            $('#search-input').keyup(function() {
                let q = $(this).val();
                if (q.length > 2) {
                    $.get('/search-users?q=' + q, function(data) {
                        $('#user-list').empty();
                        data.forEach(user => {
                            $('#user-list').append(`<li onclick="loadChat(${user.id})">${user.name}</li>`);
                        });
                    });
                }
            });
        });
    </script>
    <!-- <script>
        let currentChatUser = null;
        let lastMessageId = 0;
        let pollInterval;
        const authUserId = {{ auth()->id() }};

        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });

    function appendMessage(msg) {
        let html;
        if (msg.type === 'text') {
            html = `<p>${msg.content}</p>`;
        } else if (msg.type === 'image') {
            html = `<img src="/storage/${msg.content}" width="200">`;
        } else if (msg.type === 'video') {
            html = `<video src="/storage/${msg.content}" width="200" controls></video>`;
        }

        const className = (msg.from_user_id === authUserId) ? 'sent' : 'received';
        $('#messages').append(`<div class="message ${className}">${html}</div>`);

        const chatWindow = document.getElementById('chat-window');
        if (chatWindow) {
            chatWindow.scrollTop = chatWindow.scrollHeight;
        }
    }


        function loadChat(userId) {
            currentChatUser = userId;
            $('#messages').empty();
            $.get('/messages/' + userId, function(data) {
                data.forEach(msg => appendMessage(msg));
                lastMessageId = data.length > 0 ? data[data.length - 1].id : 0;
            });
            clearInterval(pollInterval);
            pollInterval = setInterval(function() {
                $.get('/messages/' + userId + '?last=' + lastMessageId, function(data) {
                    if (data.length > 0) {
                        data.forEach(msg => appendMessage(msg));
                        lastMessageId = data[data.length - 1].id;
                    }
                });
            }, 2000);
        }

        function handleAjaxError(xhr) {
            if (xhr.status === 401 || xhr.status === 403) {
                alert('You are not authenticated. Redirecting to login...');
                window.location.href = '/login';
            } else {
                alert('An error occurred: ' + xhr.responseJSON.message);
            }
        }

        $(document).ready(function() {
            $('#search-input').keyup(function() {
                let q = $(this).val();
                if (q.length > 2) {
                    $.get('/search-users?q=' + q, function(data) {
                        $('#user-list').empty();
                        data.forEach(user => {
                            $('#user-list').append(`<li onclick="loadChat(${user.id})">${user.name}</li>`);
                        });
                    });
                }
            });

            $('#send-btn').click(function() {
                let content = $('#message-input').val();
                if (content && currentChatUser) {
                    $.ajax({
                        url: '/messages',
                        type: 'POST',
                        data: { to_user_id: currentChatUser, content: content },
                        success: function(data) {
                            appendMessage(data);
                            $('#message-input').val('');
                        },
                        error: handleAjaxError
                    });
                }
            });

            $('#file-input').change(function() {
                if (currentChatUser) {
                    let file = this.files[0];
                    let validTypes = ['image/jpeg', 'image/png', 'image/gif', 'video/mp4', 'video/webm'];
                    if (!validTypes.includes(file.type)) {
                        alert('Invalid file type. Please upload JPG, PNG, GIF, MP4, or WebM files.');
                        return;
                    }
                    if (file.size > 10240 * 1024) {
                        alert('File is too large. Maximum size is 10MB.');
                        return;
                    }
                    let formData = new FormData();
                    formData.append('file', file);
                    formData.append('to_user_id', currentChatUser);
                    $.ajax({
                        url: '/messages',
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(data) {
                            appendMessage(data);
                            $('#file-input').val('');
                        },
                        error: handleAjaxError
                    });
                }
            });
        });
    </script> -->
</body>
</html>
