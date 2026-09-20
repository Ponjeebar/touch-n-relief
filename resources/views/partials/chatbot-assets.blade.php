<link rel="stylesheet" href="{{ asset('css/chatbot.css') }}">
<script defer src="{{ asset('js/chatbot.js') }}" data-chatbot-url="{{ route('chatbot.reply') }}" data-chatbot-csrf="{{ csrf_token() }}"></script>
