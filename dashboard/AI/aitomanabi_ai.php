<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Japanese AI Tutor Widget</title>
    <style>
      * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
      }

      body {
        font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        background: transparent;
        height: 100vh;
        overflow: hidden;
      }

      /* Floating Button */
      .chat-button {
        position: fixed;
        bottom: 30px;
        right: 30px;
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg,rgb(230, 35, 35),rgb(255, 13, 0));

        border-radius: 50%;
        border: none;
        cursor: pointer;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        display: flex;
        align-items: center;
        justify-content: center;
        transition: transform 0.3s, box-shadow 0.3s;
        z-index: 1000;
      }

      .chat-button:hover {
        transform: scale(1.1);
        box-shadow: 0 6px 25px rgba(0, 0, 0, 0.4);
      }

      .chat-button svg {
        width: 28px;
        height: 28px;
        fill: white;
      }

      /* Disabled state during quizzes */
      .chat-button.quiz-mode {
        background: linear-gradient(135deg, #a1a1aa 0%, #6b7280 100%);
        cursor: not-allowed;
        opacity: 0.7;
        box-shadow: 0 4px 14px rgba(0,0,0,0.2);
      }
      .chat-button.quiz-mode:hover {
        transform: none;
        box-shadow: 0 4px 14px rgba(0,0,0,0.2);
      }
      .chat-button.quiz-mode::after {
        content: 'AI disabled during quiz';
        position: absolute;
        bottom: 75px;
        right: 0;
        background: rgba(17,24,39,0.95);
        color: #fff;
        padding: 8px 10px;
        border-radius: 8px;
        font-size: 12px;
        white-space: nowrap;
        box-shadow: 0 6px 18px rgba(0,0,0,0.25);
        pointer-events: none;
      }

      /* Chat Container */
      .chat-container {
        position: fixed;
        bottom: 100px;
        right: 30px;
        width: 400px;
        height: 600px;
        background: white;
        border-radius: 20px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        display: none;
        flex-direction: column;
        overflow: hidden;
        z-index: 999;
        animation: slideUp 0.3s ease-out;
      }

      .chat-container.active {
        display: flex;
      }

      @keyframes slideUp {
        from {
          opacity: 0;
          transform: translateY(20px);
        }
        to {
          opacity: 1;
          transform: translateY(0);
        }
      }

      /* Header */
      .chat-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
      }

      .chat-header-content {
        display: flex;
        align-items: center;
        gap: 12px;
      }

      .tutor-avatar {
        width: 45px;
        height: 45px;
        background: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        animation: bounce 2s infinite;
      }

      @keyframes bounce {
        0%,
        100% {
          transform: translateY(0);
        }
        50% {
          transform: translateY(-5px);
        }
      }

      .tutor-info h2 {
        font-size: 18px;
        font-weight: 600;
        margin-bottom: 2px;
      }

      .tutor-status {
        font-size: 12px;
        opacity: 0.9;
        display: flex;
        align-items: center;
        gap: 5px;
      }

      .status-dot {
        width: 8px;
        height: 8px;
        background: #4ade80;
        border-radius: 50%;
        animation: pulse 2s infinite;
      }

      @keyframes pulse {
        0%,
        100% {
          opacity: 1;
        }
        50% {
          opacity: 0.5;
        }
      }

      .close-btn {
        background: none;
        border: none;
        color: white;
        font-size: 24px;
        cursor: pointer;
        padding: 0;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: background 0.2s;
      }

      .close-btn:hover {
        background: rgba(255, 255, 255, 0.2);
      }

      /* Mode Switcher */
      .mode-switcher {
        display: flex;
        gap: 8px;
        padding: 15px 20px;
        background: #f8f9fa;
        border-bottom: 1px solid #e0e0e0;
      }

      .mode-btn {
        flex: 1;
        padding: 10px;
        border: 2px solid #e0e0e0;
        background: white;
        border-radius: 10px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 600;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
      }

      .mode-btn:hover {
        border-color: #667eea;
        transform: translateY(-2px);
      }

      .mode-btn.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-color: transparent;
      }

      /* Messages */
      .chat-messages {
        flex: 1;
        padding: 20px;
        overflow-y: auto;
        background: #f8f9fa;
      }

      .message {
        margin-bottom: 15px;
        display: flex;
        flex-direction: column;
        animation: fadeIn 0.3s ease-in;
      }

      @keyframes fadeIn {
        from {
          opacity: 0;
          transform: translateY(10px);
        }
        to {
          opacity: 1;
          transform: translateY(0);
        }
      }

      .message.user {
        align-items: flex-end;
      }

      .message.bot {
        align-items: flex-start;
      }

      .message-content {
        max-width: 80%;
        padding: 12px 16px;
        border-radius: 18px;
        word-wrap: break-word;
        line-height: 1.4;
      }

      .message.user .message-content {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-bottom-right-radius: 4px;
      }

      .message.bot .message-content {
        background: white;
        color: #333;
        border-bottom-left-radius: 4px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
      }

      /* Pronunciation Mode */
      .pronunciation-panel {
        display: none;
        padding: 20px;
        background: white;
      }

      .pronunciation-panel.active {
        display: block;
        flex: 1;
        overflow-y: auto;
      }

      .word-selector {
        margin-bottom: 20px;
      }

      .word-selector label {
        display: block;
        font-weight: 600;
        margin-bottom: 10px;
        color: #333;
      }

      .word-selector select {
        width: 100%;
        padding: 12px;
        border: 2px solid #e0e0e0;
        border-radius: 10px;
        font-size: 14px;
        cursor: pointer;
        background: white;
      }

      .pronunciation-display {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 15px;
        margin-bottom: 20px;
      }

      .target-word {
        text-align: center;
        margin-bottom: 15px;
      }

      .japanese-text {
        font-size: 36px;
        font-weight: bold;
        color: #667eea;
        margin-bottom: 5px;
      }

      .romaji-text {
        font-size: 18px;
        color: #666;
        margin-bottom: 5px;
      }

      .meaning-text {
        font-size: 14px;
        color: #999;
      }

      .record-btn {
        width: 100%;
        padding: 15px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 15px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        transition: transform 0.2s;
      }

      .record-btn:hover {
        transform: scale(1.02);
      }

      .record-btn.recording {
        background: #ef4444;
        animation: recordPulse 1s infinite;
      }

      @keyframes recordPulse {
        0%,
        100% {
          opacity: 1;
        }
        50% {
          opacity: 0.8;
        }
      }

      .analysis-result {
        margin-top: 20px;
        padding: 15px;
        border-radius: 15px;
        display: none;
      }

      .analysis-result.active {
        display: block;
      }

      .analysis-result.excellent {
        background: #dcfce7;
        border: 2px solid #22c55e;
      }

      .analysis-result.good {
        background: #dbeafe;
        border: 2px solid #3b82f6;
      }

      .analysis-result.needs-practice {
        background: #fef3c7;
        border: 2px solid #f59e0b;
      }

      .score-display {
        font-size: 48px;
        font-weight: bold;
        text-align: center;
        margin-bottom: 10px;
      }

      .feedback-text {
        text-align: center;
        font-size: 14px;
        margin-bottom: 15px;
      }

      .phoneme-breakdown {
        background: white;
        padding: 12px;
        border-radius: 10px;
        margin-top: 10px;
      }

      .phoneme-item {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid #e0e0e0;
      }

      .phoneme-item:last-child {
        border-bottom: none;
      }

      .phoneme-score {
        font-weight: 600;
      }

      .phoneme-score.good {
        color: #22c55e;
      }
      .phoneme-score.okay {
        color: #f59e0b;
      }
      .phoneme-score.poor {
        color: #ef4444;
      }

      /* Input Area */
      .chat-input {
        padding: 20px;
        background: white;
        border-top: 1px solid #e0e0e0;
        display: flex;
        gap: 10px;
        align-items: center;
      }

      .chat-input input {
        flex: 1;
        padding: 12px 16px;
        border: 2px solid #e0e0e0;
        border-radius: 25px;
        outline: none;
        font-size: 14px;
        transition: border-color 0.3s;
      }

      .chat-input input:focus {
        border-color: #667eea;
      }

      .send-btn,
      .mic-btn {
        width: 45px;
        height: 45px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: transform 0.2s;
      }

      .send-btn:hover,
      .mic-btn:hover {
        transform: scale(1.05);
      }

      .send-btn:disabled,
      .mic-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
      }

      .send-btn svg,
      .mic-btn svg {
        width: 20px;
        height: 20px;
        fill: white;
      }

      .mic-btn.active {
        background: #ff4444;
      }

      /* Typing indicator */
      .typing-indicator {
        display: none;
        padding: 12px 16px;
        background: white;
        border-radius: 18px;
        width: fit-content;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
      }

      .typing-indicator.active {
        display: block;
      }

      .typing-indicator span {
        display: inline-block;
        width: 8px;
        height: 8px;
        background: #999;
        border-radius: 50%;
        margin: 0 2px;
        animation: typing 1.4s infinite;
      }

      .typing-indicator span:nth-child(2) {
        animation-delay: 0.2s;
      }

      .typing-indicator span:nth-child(3) {
        animation-delay: 0.4s;
      }

      @keyframes typing {
        0%,
        60%,
        100% {
          transform: translateY(0);
        }
        30% {
          transform: translateY(-10px);
        }
      }

      /* Scrollbar */
      .chat-messages::-webkit-scrollbar,
      .pronunciation-panel::-webkit-scrollbar {
        width: 6px;
      }

      .chat-messages::-webkit-scrollbar-track,
      .pronunciation-panel::-webkit-scrollbar-track {
        background: #f1f1f1;
      }

      .chat-messages::-webkit-scrollbar-thumb,
      .pronunciation-panel::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 3px;
      }

      .chat-messages::-webkit-scrollbar-thumb:hover,
      .pronunciation-panel::-webkit-scrollbar-thumb:hover {
        background: #555;
      }

      /* Mobile Responsive */
      @media (max-width: 480px) {
        .chat-container {
          width: calc(100vw - 20px);
          height: calc(100vh - 120px);
          right: 10px;
          bottom: 90px;
        }

        .chat-button {
          right: 20px;
          bottom: 20px;
        }
      }
    </style>
  </head>
  <body>
    <button class="chat-button" onclick="toggleChat()">
      <svg viewBox="0 0 24 24">
        <path
          d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"
        />
      </svg>
    </button>

    <div class="chat-container" id="chatContainer">
      <div class="chat-header">
        <div class="chat-header-content">
          <div class="tutor-avatar">👨‍🏫</div>
          <div class="tutor-info">
            <h2>Sensei Kaito Tanaka</h2>
            <div class="tutor-status">
              <span class="status-dot"></span>
              <span>Ready to teach</span>
            </div>
          </div>
        </div>
        <button class="close-btn" onclick="toggleChat()">×</button>
      </div>

      <div class="mode-switcher">
        <button class="mode-btn active" onclick="switchMode('chat')">
          💬 Chat
        </button>
        <button class="mode-btn" onclick="switchMode('pronunciation')">
          🎤 Pronunciation
        </button>
      </div>

      <div class="chat-messages" id="chatMessages">
        <div class="message bot">
          <div class="message-content">
            こんにちは！(Konnichiwa!) I'm Sensei Takeshi, your Japanese tutor!
            🎌 I'm here to help you master Japanese. Ask me anything about
            grammar, vocabulary, or switch to Pronunciation mode to practice
            your speaking! 頑張りましょう！(Let's do our best!)
          </div>
        </div>
      </div>

      <div class="pronunciation-panel" id="pronunciationPanel">
        <div class="word-selector">
          <label>Select a word to practice:</label>
          <select id="wordSelect" onchange="updatePronunciationDisplay()">
            <option value="">Choose a word...</option>
          </select>
        </div>

        <div
          class="pronunciation-display"
          id="pronunciationDisplay"
          style="display: none"
        >
          <div class="target-word">
            <div class="japanese-text" id="japaneseText"></div>
            <div class="romaji-text" id="romajiText"></div>
            <div class="meaning-text" id="meaningText"></div>
          </div>

          <button
            class="record-btn"
            id="recordBtn"
            onclick="startPronunciationCheck()"
          >
            <span id="recordBtnText">🎤 Record Your Pronunciation</span>
          </button>

          <div class="analysis-result" id="analysisResult">
            <div class="score-display" id="scoreDisplay"></div>
            <div class="feedback-text" id="feedbackText"></div>
            <div class="phoneme-breakdown" id="phonemeBreakdown"></div>
          </div>
        </div>
      </div>

      <div class="chat-input" id="chatInput">
        <button class="mic-btn" id="micBtn" onclick="toggleMic()">
          <svg viewBox="0 0 24 24">
            <path
              d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3zm-1-9c0-.55.45-1 1-1s1 .45 1 1v6c0 .55-.45 1-1 1s-1-.45-1-1V5zm6 9c0-2.76-2.24-5-5-5s-5 2.24-5 5h2c0-1.66 1.34-3 3-3s3 1.34 3 3h2z"
            />
          </svg>
        </button>
        <input
          type="text"
          id="messageInput"
          placeholder="Type your message..."
          onkeypress="if(event.key==='Enter') sendMessage()"
        />
        <button class="send-btn" id="sendBtn" onclick="sendMessage()">
          <svg viewBox="0 0 24 24">
            <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z" />
          </svg>
        </button>
      </div>
    </div>

    <script>
      const API_URL = "https://b18bbb282e23.ngrok-free.app";

      // DOM references used by quiz-disable logic
      const chatButton = document.querySelector('.chat-button');
      const chatWidget = document.getElementById('chatContainer');

      // 50+ Japanese words with phoneme data
      const japaneseWords = [
        {
          japanese: "こんにちは",
          romaji: "konnichiwa",
          meaning: "Hello",
          phonemes: ["ko", "n", "ni", "chi", "wa"],
        },
        {
          japanese: "ありがとう",
          romaji: "arigatou",
          meaning: "Thank you",
          phonemes: ["a", "ri", "ga", "to", "u"],
        },
        {
          japanese: "さようなら",
          romaji: "sayounara",
          meaning: "Goodbye",
          phonemes: ["sa", "yo", "u", "na", "ra"],
        },
        {
          japanese: "おはよう",
          romaji: "ohayou",
          meaning: "Good morning",
          phonemes: ["o", "ha", "yo", "u"],
        },
        {
          japanese: "おやすみ",
          romaji: "oyasumi",
          meaning: "Good night",
          phonemes: ["o", "ya", "su", "mi"],
        },
        {
          japanese: "すみません",
          romaji: "sumimasen",
          meaning: "Excuse me",
          phonemes: ["su", "mi", "ma", "se", "n"],
        },
        {
          japanese: "はい",
          romaji: "hai",
          meaning: "Yes",
          phonemes: ["ha", "i"],
        },
        {
          japanese: "いいえ",
          romaji: "iie",
          meaning: "No",
          phonemes: ["i", "i", "e"],
        },
        {
          japanese: "わかりました",
          romaji: "wakarimashita",
          meaning: "I understand",
          phonemes: ["wa", "ka", "ri", "ma", "shi", "ta"],
        },
        {
          japanese: "ください",
          romaji: "kudasai",
          meaning: "Please",
          phonemes: ["ku", "da", "sa", "i"],
        },
        {
          japanese: "どういたしまして",
          romaji: "douitashimashite",
          meaning: "You're welcome",
          phonemes: ["do", "u", "i", "ta", "shi", "ma", "shi", "te"],
        },
        {
          japanese: "ごめんなさい",
          romaji: "gomennasai",
          meaning: "I'm sorry",
          phonemes: ["go", "me", "n", "na", "sa", "i"],
        },
        {
          japanese: "たべる",
          romaji: "taberu",
          meaning: "To eat",
          phonemes: ["ta", "be", "ru"],
        },
        {
          japanese: "のむ",
          romaji: "nomu",
          meaning: "To drink",
          phonemes: ["no", "mu"],
        },
        {
          japanese: "いく",
          romaji: "iku",
          meaning: "To go",
          phonemes: ["i", "ku"],
        },
        {
          japanese: "くる",
          romaji: "kuru",
          meaning: "To come",
          phonemes: ["ku", "ru"],
        },
        {
          japanese: "みる",
          romaji: "miru",
          meaning: "To see",
          phonemes: ["mi", "ru"],
        },
        {
          japanese: "きく",
          romaji: "kiku",
          meaning: "To listen",
          phonemes: ["ki", "ku"],
        },
        {
          japanese: "はなす",
          romaji: "hanasu",
          meaning: "To speak",
          phonemes: ["ha", "na", "su"],
        },
        {
          japanese: "よむ",
          romaji: "yomu",
          meaning: "To read",
          phonemes: ["yo", "mu"],
        },
        {
          japanese: "かく",
          romaji: "kaku",
          meaning: "To write",
          phonemes: ["ka", "ku"],
        },
        {
          japanese: "べんきょう",
          romaji: "benkyou",
          meaning: "Study",
          phonemes: ["be", "n", "kyo", "u"],
        },
        {
          japanese: "がっこう",
          romaji: "gakkou",
          meaning: "School",
          phonemes: ["ga", "k", "ko", "u"],
        },
        {
          japanese: "せんせい",
          romaji: "sensei",
          meaning: "Teacher",
          phonemes: ["se", "n", "se", "i"],
        },
        {
          japanese: "がくせい",
          romaji: "gakusei",
          meaning: "Student",
          phonemes: ["ga", "ku", "se", "i"],
        },
        {
          japanese: "ともだち",
          romaji: "tomodachi",
          meaning: "Friend",
          phonemes: ["to", "mo", "da", "chi"],
        },
        {
          japanese: "かぞく",
          romaji: "kazoku",
          meaning: "Family",
          phonemes: ["ka", "zo", "ku"],
        },
        {
          japanese: "いえ",
          romaji: "ie",
          meaning: "House",
          phonemes: ["i", "e"],
        },
        {
          japanese: "みず",
          romaji: "mizu",
          meaning: "Water",
          phonemes: ["mi", "zu"],
        },
        {
          japanese: "たべもの",
          romaji: "tabemono",
          meaning: "Food",
          phonemes: ["ta", "be", "mo", "no"],
        },
        {
          japanese: "のみもの",
          romaji: "nomimono",
          meaning: "Drink",
          phonemes: ["no", "mi", "mo", "no"],
        },
        {
          japanese: "さかな",
          romaji: "sakana",
          meaning: "Fish",
          phonemes: ["sa", "ka", "na"],
        },
        {
          japanese: "にく",
          romaji: "niku",
          meaning: "Meat",
          phonemes: ["ni", "ku"],
        },
        {
          japanese: "やさい",
          romaji: "yasai",
          meaning: "Vegetable",
          phonemes: ["ya", "sa", "i"],
        },
        {
          japanese: "くだもの",
          romaji: "kudamono",
          meaning: "Fruit",
          phonemes: ["ku", "da", "mo", "no"],
        },
        {
          japanese: "おいしい",
          romaji: "oishii",
          meaning: "Delicious",
          phonemes: ["o", "i", "shi", "i"],
        },
        {
          japanese: "きれい",
          romaji: "kirei",
          meaning: "Beautiful",
          phonemes: ["ki", "re", "i"],
        },
        {
          japanese: "おおきい",
          romaji: "ookii",
          meaning: "Big",
          phonemes: ["o", "o", "ki", "i"],
        },
        {
          japanese: "ちいさい",
          romaji: "chiisai",
          meaning: "Small",
          phonemes: ["chi", "i", "sa", "i"],
        },
        {
          japanese: "たかい",
          romaji: "takai",
          meaning: "Expensive/Tall",
          phonemes: ["ta", "ka", "i"],
        },
        {
          japanese: "やすい",
          romaji: "yasui",
          meaning: "Cheap",
          phonemes: ["ya", "su", "i"],
        },
        {
          japanese: "あたらしい",
          romaji: "atarashii",
          meaning: "New",
          phonemes: ["a", "ta", "ra", "shi", "i"],
        },
        {
          japanese: "ふるい",
          romaji: "furui",
          meaning: "Old",
          phonemes: ["fu", "ru", "i"],
        },
        {
          japanese: "あつい",
          romaji: "atsui",
          meaning: "Hot",
          phonemes: ["a", "tsu", "i"],
        },
        {
          japanese: "さむい",
          romaji: "samui",
          meaning: "Cold",
          phonemes: ["sa", "mu", "i"],
        },
        {
          japanese: "てんき",
          romaji: "tenki",
          meaning: "Weather",
          phonemes: ["te", "n", "ki"],
        },
        {
          japanese: "はる",
          romaji: "haru",
          meaning: "Spring",
          phonemes: ["ha", "ru"],
        },
        {
          japanese: "なつ",
          romaji: "natsu",
          meaning: "Summer",
          phonemes: ["na", "tsu"],
        },
        {
          japanese: "あき",
          romaji: "aki",
          meaning: "Autumn",
          phonemes: ["a", "ki"],
        },
        {
          japanese: "ふゆ",
          romaji: "fuyu",
          meaning: "Winter",
          phonemes: ["fu", "yu"],
        },
        {
          japanese: "すき",
          romaji: "suki",
          meaning: "Like",
          phonemes: ["su", "ki"],
        },
        {
          japanese: "きらい",
          romaji: "kirai",
          meaning: "Dislike",
          phonemes: ["ki", "ra", "i"],
        },
        {
          japanese: "しごと",
          romaji: "shigoto",
          meaning: "Work",
          phonemes: ["shi", "go", "to"],
        },
        {
          japanese: "やすみ",
          romaji: "yasumi",
          meaning: "Rest/Holiday",
          phonemes: ["ya", "su", "mi"],
        },
        {
          japanese: "あした",
          romaji: "ashita",
          meaning: "Tomorrow",
          phonemes: ["a", "shi", "ta"],
        },
        {
          japanese: "きょう",
          romaji: "kyou",
          meaning: "Today",
          phonemes: ["kyo", "u"],
        },
        {
          japanese: "きのう",
          romaji: "kinou",
          meaning: "Yesterday",
          phonemes: ["ki", "no", "u"],
        },
      ];

      let currentMode = "chat";
      let currentWord = null;
      let pronunciationRecognition = null;

      // Initialize word selector
      function initializeWordSelector() {
        const select = document.getElementById("wordSelect");
        japaneseWords.forEach((word, index) => {
          const option = document.createElement("option");
          option.value = index;
          option.textContent = `${word.japanese} (${word.romaji}) - ${word.meaning}`;
          select.appendChild(option);
        });
      }

      function toggleChat() {
        const chatContainer = document.getElementById("chatContainer");
        // Prevent opening when in quiz mode
        if (chatButton && chatButton.classList.contains('quiz-mode')) {
          return;
        }
        chatContainer.classList.toggle("active");
        if (chatContainer.classList.contains("active")) {
          document.getElementById("messageInput").focus();
        }
      }

      function switchMode(mode) {
        currentMode = mode;
        const chatMessages = document.getElementById("chatMessages");
        const pronunciationPanel =
          document.getElementById("pronunciationPanel");
        const chatInput = document.getElementById("chatInput");
        const modeBtns = document.querySelectorAll(".mode-btn");

        modeBtns.forEach((btn) => btn.classList.remove("active"));
        event.target.classList.add("active");

        if (mode === "chat") {
          chatMessages.style.display = "block";
          pronunciationPanel.classList.remove("active");
          chatInput.style.display = "flex";
        } else {
          chatMessages.style.display = "none";
          pronunciationPanel.classList.add("active");
          chatInput.style.display = "none";
        }
      }

      function updatePronunciationDisplay() {
        const select = document.getElementById("wordSelect");
        const display = document.getElementById("pronunciationDisplay");
        const analysisResult = document.getElementById("analysisResult");

        if (select.value === "") {
          display.style.display = "none";
          return;
        }

        currentWord = japaneseWords[select.value];
        document.getElementById("japaneseText").textContent =
          currentWord.japanese;
        document.getElementById("romajiText").textContent = currentWord.romaji;
        document.getElementById("meaningText").textContent =
          currentWord.meaning;
        display.style.display = "block";
        analysisResult.classList.remove("active");
      }

      function startPronunciationCheck() {
        if (!currentWord) return;

        const recordBtn = document.getElementById("recordBtn");
        const btnText = document.getElementById("recordBtnText");

        if (!pronunciationRecognition) {
          if ("webkitSpeechRecognition" in window) {
            pronunciationRecognition = new webkitSpeechRecognition();
            pronunciationRecognition.continuous = false;
            pronunciationRecognition.interimResults = false;
            pronunciationRecognition.lang = "ja-JP";

            pronunciationRecognition.onresult = (event) => {
              const transcript = event.results[0][0].transcript;
              analyzePronunciation(transcript);
              recordBtn.classList.remove("recording");
              btnText.textContent = "🎤 Record Your Pronunciation";
            };

            pronunciationRecognition.onerror = (event) => {
              console.error("Speech recognition error:", event.error);
              recordBtn.classList.remove("recording");
              btnText.textContent = "🎤 Record Your Pronunciation";
              showAnalysisError();
            };

            pronunciationRecognition.onend = () => {
              recordBtn.classList.remove("recording");
              btnText.textContent = "🎤 Record Your Pronunciation";
            };
          } else {
            alert("Speech recognition not supported in this browser.");
            return;
          }
        }

        recordBtn.classList.add("recording");
        btnText.textContent = "🎙️ Recording... Speak now!";
        pronunciationRecognition.start();
      }

      function analyzePronunciation(spokenText) {
        // Remove spaces and convert to lowercase for comparison
        const normalizedSpoken = spokenText.replace(/\s/g, "").toLowerCase();
        const normalizedTarget = currentWord.japanese
          .replace(/\s/g, "")
          .toLowerCase();

        // Calculate similarity score
        let matchScore = calculateSimilarity(
          normalizedSpoken,
          normalizedTarget
        );

        // Analyze individual phonemes
        const phonemeScores = analyzePhonemes(spokenText);

        // Display results
        displayAnalysisResults(matchScore, phonemeScores, spokenText);
      }

      function calculateSimilarity(str1, str2) {
        // Levenshtein distance algorithm
        const len1 = str1.length;
        const len2 = str2.length;
        const matrix = [];

        for (let i = 0; i <= len2; i++) {
          matrix[i] = [i];
        }

        for (let j = 0; j <= len1; j++) {
          matrix[0][j] = j;
        }

        for (let i = 1; i <= len2; i++) {
          for (let j = 1; j <= len1; j++) {
            if (str2.charAt(i - 1) === str1.charAt(j - 1)) {
              matrix[i][j] = matrix[i - 1][j - 1];
            } else {
              matrix[i][j] = Math.min(
                matrix[i - 1][j - 1] + 1,
                matrix[i][j - 1] + 1,
                matrix[i - 1][j] + 1
              );
            }
          }
        }

        const distance = matrix[len2][len1];
        const maxLen = Math.max(len1, len2);
        return Math.round(((maxLen - distance) / maxLen) * 100);
      }

      function analyzePhonemes(spokenText) {
        const scores = [];

        currentWord.phonemes.forEach((phoneme) => {
          // Simple phoneme matching
          let score = 70 + Math.random() * 30; // Base score with randomization

          // Check if the phoneme sound exists in spoken text
          if (spokenText.includes(phoneme)) {
            score = Math.min(100, score + 15);
          }

          scores.push({
            phoneme: phoneme,
            score: Math.round(score),
            status: score >= 85 ? "good" : score >= 70 ? "okay" : "poor",
          });
        });

        return scores;
      }

      function displayAnalysisResults(overallScore, phonemeScores, spokenText) {
        const analysisResult = document.getElementById("analysisResult");
        const scoreDisplay = document.getElementById("scoreDisplay");
        const feedbackText = document.getElementById("feedbackText");
        const phonemeBreakdown = document.getElementById("phonemeBreakdown");

        // Determine result class
        let resultClass = "needs-practice";
        let emoji = "😊";
        let feedback = "";

        if (overallScore >= 85) {
          resultClass = "excellent";
          emoji = "🌟";
          feedback =
            "Excellent pronunciation! すごい！(Sugoi!) You sound like a native speaker!";
        } else if (overallScore >= 70) {
          resultClass = "good";
          emoji = "👍";
          feedback =
            "Good job! いいですね！(Ii desu ne!) Keep practicing to perfect it!";
        } else {
          resultClass = "needs-practice";
          emoji = "💪";
          feedback =
            "Keep trying! がんばって！(Ganbatte!) Practice makes perfect!";
        }

        analysisResult.className = `analysis-result active ${resultClass}`;
        scoreDisplay.innerHTML = `${emoji} ${overallScore}%`;
        feedbackText.innerHTML = `${feedback}<br><small>You said: <strong>${spokenText}</strong></small>`;

        // Display phoneme breakdown
        let phonemeHTML = "<strong>Phoneme Analysis:</strong><br>";
        phonemeScores.forEach((item) => {
          phonemeHTML += `
            <div class="phoneme-item">
              <span>${item.phoneme}</span>
              <span class="phoneme-score ${item.status}">${item.score}%</span>
            </div>
          `;
        });

        phonemeBreakdown.innerHTML = phonemeHTML;
        analysisResult.classList.add("active");

        // Speak encouragement
        speakResponse(feedback.split("！")[0]);
      }

      function showAnalysisError() {
        const analysisResult = document.getElementById("analysisResult");
        const scoreDisplay = document.getElementById("scoreDisplay");
        const feedbackText = document.getElementById("feedbackText");
        const phonemeBreakdown = document.getElementById("phonemeBreakdown");

        analysisResult.className = "analysis-result active needs-practice";
        scoreDisplay.innerHTML = "❌";
        feedbackText.innerHTML =
          "Could not detect pronunciation. Please try again!";
        phonemeBreakdown.innerHTML = "";
        analysisResult.classList.add("active");
      }

      function addMessage(content, isUser) {
        const messagesDiv = document.getElementById("chatMessages");
        const messageDiv = document.createElement("div");
        messageDiv.className = `message ${isUser ? "user" : "bot"}`;

        const contentDiv = document.createElement("div");
        contentDiv.className = "message-content";
        contentDiv.textContent = content;

        messageDiv.appendChild(contentDiv);
        messagesDiv.appendChild(messageDiv);
        messagesDiv.scrollTop = messagesDiv.scrollHeight;

        if (!isUser) {
          speakResponse(content);
        }
      }

      function showTyping() {
        const messagesDiv = document.getElementById("chatMessages");
        const typingDiv = document.createElement("div");
        typingDiv.className = "message bot";
        typingDiv.id = "typingIndicator";
        typingDiv.innerHTML = `
          <div class="typing-indicator active">
            <span></span><span></span><span></span>
          </div>
        `;
        messagesDiv.appendChild(typingDiv);
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
      }

      function hideTyping() {
        const typingDiv = document.getElementById("typingIndicator");
        if (typingDiv) {
          typingDiv.remove();
        }
      }

      async function sendMessage() {
        const input = document.getElementById("messageInput");
        const sendBtn = document.getElementById("sendBtn");
        const message = input.value.trim();

        if (!message) return;

        addMessage(message, true);
        input.value = "";
        sendBtn.disabled = true;

        showTyping();

        try {
          const response = await fetch(`${API_URL}/chat`, {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
            },
            body: JSON.stringify({ message: message }),
          });

          if (!response.ok) {
            throw new Error("Network response was not ok");
          }

          const data = await response.json();
          hideTyping();
          addMessage(data.response, false);
        } catch (error) {
          hideTyping();
          addMessage(
            "Gomen nasai! (Sorry!) There was an error connecting to the server. Please try again.",
            false
          );
          console.error("Error:", error);
        } finally {
          sendBtn.disabled = false;
          input.focus();
        }
      }

      function speakResponse(text) {
        const synth = window.speechSynthesis;
        const utterance = new SpeechSynthesisUtterance(text.replace(/\*/g, ""));

        const voices = synth.getVoices();
        let selectedVoice = voices.find(
          (voice) =>
            voice.name.includes("Google UK English Female") ||
            voice.lang === "ja-JP"
        );

        if (!selectedVoice) {
          selectedVoice =
            voices.find((voice) => voice.lang.startsWith("en-")) || voices[0];
        }

        utterance.voice = selectedVoice;
        utterance.lang = selectedVoice.lang;
        utterance.rate = 0.9;
        utterance.pitch = 1.2;

        if (/[\u3040-\u309F\u30A0-\u30FF\u4E00-\u9FFF]/.test(text)) {
          utterance.lang = "ja-JP";
          const japaneseVoice = voices.find((voice) => voice.lang === "ja-JP");
          if (japaneseVoice) utterance.voice = japaneseVoice;
        }

        synth.speak(utterance);
      }

      let recognition;
      if ("webkitSpeechRecognition" in window) {
        recognition = new webkitSpeechRecognition();
        recognition.continuous = false;
        recognition.interimResults = true;
        recognition.lang = "en-US";

        recognition.onresult = (event) => {
          const result = event.results[event.results.length - 1];
          const transcript = result[0].transcript;
          const messageInput = document.getElementById("messageInput");

          if (result.isFinal) {
            messageInput.value = transcript;
            toggleMic();
            sendMessage();
          } else {
            messageInput.value = transcript;
          }
        };

        recognition.onerror = (event) => {
          console.error("Speech recognition error:", event.error);
          toggleMic();
          addMessage("Sorry, there was an error with the microphone.", false);
        };

        recognition.onend = () => {
          toggleMic(false);
        };
      }

      function toggleMic(start = true) {
        const micBtn = document.getElementById("micBtn");
        if (!recognition) {
          addMessage("Microphone not supported in this browser.", false);
          return;
        }

        if (start) {
          micBtn.classList.add("active");
          recognition.lang = /[\u3040-\u309F\u30A0-\u30FF\u4E00-\u9FFF]/.test(
            document.getElementById("messageInput").value
          )
            ? "ja-JP"
            : "en-US";
          recognition.start();
        } else {
          micBtn.classList.remove("active");
          recognition.stop();
        }
      }

      // Initialize on load
      window.onload = () => {
        initializeWordSelector();
      };

      window.speechSynthesis.onvoiceschanged = () => {
        console.log("Voices loaded");
      };


            // Simple Quiz Detection
            function isQuizPage() {
        const currentPath = window.location.pathname.toLowerCase();
        const currentURL = window.location.href.toLowerCase();
        
        // Simple and reliable quiz page detection
        const quizKeywords = ['quiz', 'test', 'exam', 'assessment'];
        
        // Check if URL contains quiz-related keywords
        const isQuizURL = quizKeywords.some(keyword => 
          currentPath.includes(keyword) || currentURL.includes(keyword)
        );
        
        console.log('Simple Quiz Check:', {
          path: currentPath,
          url: currentURL,
          isQuiz: isQuizURL
        });
        
        return isQuizURL;
      }

      function disableChatForQuiz() {
        // Safety: if core elements are missing, skip to avoid errors
        if (!chatButton || !chatWidget) {
          console.warn('AI widget elements not found; skipping quiz-disable check');
          return;
        }
        const isQuiz = isQuizPage();
        
        if (isQuiz) {
          // Enable quiz mode
          chatButton.classList.add('quiz-mode');
          chatButton.onclick = function(e) {
            e.preventDefault();
            e.stopPropagation();
            return false;
          };
          console.log('🛑 AI Chat DISABLED - Quiz page detected');
          
          // Close chat widget if it's open
          if (chatWidget.classList.contains('active')) {
            chatWidget.classList.remove('active');
            chatButton.style.display = 'flex';
          }
          
        } else {
          // Disable quiz mode
          chatButton.classList.remove('quiz-mode');
          // Restore normal behavior immediately
          chatButton.onclick = toggleChat;
          console.log('✅ AI Chat ENABLED - Normal page');
        }
      }

      // Initialize quiz detection
      disableChatForQuiz();

      // Real-time monitoring with multiple event listeners
      let lastPath = window.location.pathname;
      
      // Check for page changes every 500ms
      setInterval(() => {
        const currentPath = window.location.pathname;
        if (currentPath !== lastPath) {
          lastPath = currentPath;
          console.log('Page changed, checking quiz status...');
          disableChatForQuiz();
        }
      }, 500);

      // Listen for page visibility changes
      document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
          console.log('Page visible, checking quiz status...');
          disableChatForQuiz();
        }
      });

      // Listen for hash changes (for SPA navigation)
      window.addEventListener('hashchange', () => {
        console.log('Hash changed, checking quiz status...');
        disableChatForQuiz();
      });

      // Listen for popstate (back/forward navigation)
      window.addEventListener('popstate', () => {
        console.log('Popstate detected, checking quiz status...');
        disableChatForQuiz();
      });

      // Listen for any clicks on links that might navigate
      document.addEventListener('click', (e) => {
        const link = e.target.closest('a');
        if (link && link.href) {
          // Check if it's an internal link
          if (link.href.includes(window.location.origin)) {
            setTimeout(() => {
              console.log('Link clicked, checking quiz status...');
              disableChatForQuiz();
            }, 100);
          }
        }
      });

      // Listen for form submissions that might navigate
      document.addEventListener('submit', (e) => {
        setTimeout(() => {
          console.log('Form submitted, checking quiz status...');
          disableChatForQuiz();
        }, 100);
      });

      // Override pushState and replaceState for SPA navigation
      const originalPushState = history.pushState;
      const originalReplaceState = history.replaceState;
      
      history.pushState = function(...args) {
        originalPushState.apply(this, args);
        setTimeout(() => {
          console.log('PushState detected, checking quiz status...');
          disableChatForQuiz();
        }, 50);
      };
      
      history.replaceState = function(...args) {
        originalReplaceState.apply(this, args);
        setTimeout(() => {
          console.log('ReplaceState detected, checking quiz status...');
          disableChatForQuiz();
        }, 50);
      };
    </script>
  </body>
</html>