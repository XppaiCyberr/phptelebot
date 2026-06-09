<?php

require_once __DIR__.'/src/PHPTelebot.php';

session_start();

function panelCredentials($path)
{
    $config = [];
    if (!is_file($path)) {
        return $config;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line == '' || substr($line, 0, 1) == '#') {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $config[trim($key)] = trim($value);
        }
    }

    return $config;
}

$config = panelCredentials(__DIR__.'/x.c');
$token = getenv('TELEBOT_TOKEN') ?: (isset($config['token']) ? $config['token'] : '');
$username = getenv('TELEBOT_USERNAME') ?: (isset($config['username']) ? ltrim($config['username'], '@') : '');
$panelKey = getenv('TELEBOT_PANEL_KEY') ?: (isset($config['panel_key']) ? $config['panel_key'] : '');
$database = getenv('TELEBOT_DATABASE') ?: __DIR__.'/data/telebot.sqlite';

TelebotStorage::configure($database);

if ($panelKey != '') {
    if (isset($_POST['panel_key']) && hash_equals($panelKey, $_POST['panel_key'])) {
        $_SESSION['telebot_panel'] = true;
        header('Location: '.$_SERVER['PHP_SELF']);
        exit;
    }

    if (isset($_GET['logout'])) {
        unset($_SESSION['telebot_panel']);
        header('Location: '.$_SERVER['PHP_SELF']);
        exit;
    }

    if (empty($_SESSION['telebot_panel'])) {
        echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><script src="https://cdn.tailwindcss.com"></script><title>Telebot Panel</title></head><body class="min-h-screen bg-slate-950 text-white grid place-items-center"><form method="post" class="w-full max-w-sm rounded-3xl border border-white/10 bg-white/10 p-8 shadow-2xl backdrop-blur"><h1 class="mb-6 text-2xl font-semibold">Telebot Panel</h1><input name="panel_key" type="password" placeholder="Panel key" class="mb-4 w-full rounded-2xl border border-white/10 bg-slate-900 px-4 py-3 outline-none focus:ring-2 focus:ring-cyan-400"><button class="w-full rounded-2xl bg-cyan-400 px-4 py-3 font-semibold text-slate-950 transition hover:bg-cyan-300">Enter</button></form></body></html>';
        exit;
    }
}

if (isset($_GET['api'])) {
    header('Content-Type: application/json');

    try {
        if ($_GET['api'] == 'dashboard') {
            echo json_encode(TelebotStorage::dashboard());
            exit;
        }

        if ($_GET['api'] == 'messages') {
            $chatId = isset($_GET['chat_id']) ? $_GET['chat_id'] : null;
            echo json_encode(['messages' => TelebotStorage::messages($chatId)]);
            exit;
        }

        if ($_GET['api'] == 'reply' && $_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($token == '') {
                throw new Exception('Bot token is missing.');
            }

            PHPTelebot::$token = $token;
            $chatId = isset($_POST['chat_id']) ? trim($_POST['chat_id']) : '';
            $topicId = isset($_POST['topic_id']) ? trim($_POST['topic_id']) : '';
            $messageId = isset($_POST['message_id']) ? trim($_POST['message_id']) : '';
            $text = isset($_POST['text']) ? trim($_POST['text']) : '';

            if ($chatId == '' || $text == '') {
                throw new Exception('Chat and text are required.');
            }

            $payload = ['chat_id' => $chatId, 'text' => $text];
            if ($topicId != '') {
                $payload['message_thread_id'] = $topicId;
            }
            if ($messageId != '') {
                $payload['reply_parameters'] = ['message_id' => (int) $messageId];
            }

            $response = Bot::send('sendMessage', $payload);
            TelebotStorage::saveReply($chatId, $topicId, $messageId, $text, $response);
            echo json_encode(['ok' => true, 'response' => json_decode($response, true)]);
            exit;
        }

        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Unknown endpoint']);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Telebot Panel</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
@keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}
.fade{animation:fade .35s ease both}@keyframes fade{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
</style>
</head>
<body class="min-h-screen overflow-x-hidden bg-slate-950 text-slate-100">
<div class="pointer-events-none fixed inset-0 opacity-70"><div class="absolute left-1/4 top-0 h-72 w-72 rounded-full bg-cyan-500/20 blur-3xl" style="animation:float 7s ease-in-out infinite"></div><div class="absolute right-1/4 top-32 h-72 w-72 rounded-full bg-fuchsia-500/20 blur-3xl" style="animation:float 9s ease-in-out infinite"></div></div>
<main class="relative mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
<header class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
<div><p class="text-sm text-cyan-300">PHPTelebot</p><h1 class="text-3xl font-bold tracking-tight">Bot Control Panel</h1></div>
<div class="flex items-center gap-3"><button id="refresh" class="rounded-2xl border border-white/10 bg-white/10 px-4 py-2 text-sm transition hover:bg-white/20">Refresh</button><?php if ($panelKey != ''): ?><a href="?logout=1" class="rounded-2xl bg-white px-4 py-2 text-sm font-semibold text-slate-950">Logout</a><?php endif; ?></div>
</header>
<section id="stats" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"></section>
<section class="mt-6 grid gap-6 lg:grid-cols-3">
<div class="rounded-3xl border border-white/10 bg-white/10 p-5 shadow-2xl backdrop-blur lg:col-span-1"><div class="mb-4 flex items-center justify-between"><h2 class="font-semibold">Groups & channels</h2><span id="groupCount" class="text-xs text-slate-400"></span></div><div id="groups" class="space-y-3"></div></div>
<div class="rounded-3xl border border-white/10 bg-white/10 p-5 shadow-2xl backdrop-blur lg:col-span-2"><div class="mb-4 flex items-center justify-between"><h2 class="font-semibold">Live messages</h2><span id="activeChat" class="text-xs text-slate-400">All chats</span></div><div id="messages" class="max-h-[32rem] space-y-3 overflow-auto pr-1"></div></div>
</section>
<section class="mt-6 rounded-3xl border border-white/10 bg-white/10 p-5 shadow-2xl backdrop-blur"><h2 class="mb-4 font-semibold">Reply from web panel</h2><form id="replyForm" class="grid gap-3 lg:grid-cols-6"><input name="chat_id" id="chat_id" placeholder="Chat ID" class="rounded-2xl border border-white/10 bg-slate-900/80 px-4 py-3 outline-none focus:ring-2 focus:ring-cyan-400"><input name="topic_id" id="topic_id" placeholder="Topic ID" class="rounded-2xl border border-white/10 bg-slate-900/80 px-4 py-3 outline-none focus:ring-2 focus:ring-cyan-400"><input name="message_id" id="message_id" placeholder="Reply message ID" class="rounded-2xl border border-white/10 bg-slate-900/80 px-4 py-3 outline-none focus:ring-2 focus:ring-cyan-400"><input name="text" placeholder="Message" class="rounded-2xl border border-white/10 bg-slate-900/80 px-4 py-3 outline-none focus:ring-2 focus:ring-cyan-400 lg:col-span-2"><button class="rounded-2xl bg-cyan-400 px-4 py-3 font-semibold text-slate-950 transition hover:bg-cyan-300">Send</button></form><p id="replyStatus" class="mt-3 text-sm text-slate-400"></p></section>
</main>
<script>
const $=id=>document.getElementById(id);let selectedChat='';
function esc(v){return String(v??'').replace(/[&<>'"]/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[m]))}
function time(v){return v?new Date(v*1000).toLocaleString():'-'}
async function api(path,options){const r=await fetch(path,options);const j=await r.json();if(!r.ok)throw new Error(j.error||'Request failed');return j}
function statCard(label,value){return `<div class="fade rounded-3xl border border-white/10 bg-white/10 p-5 shadow-xl backdrop-blur transition hover:-translate-y-1 hover:bg-white/15"><p class="text-sm text-slate-400">${label}</p><p class="mt-2 text-3xl font-bold">${value}</p></div>`}
function renderGroups(groups){$('groupCount').textContent=groups.length+' visible';$('groups').innerHTML=groups.map(g=>`<button class="fade w-full rounded-2xl border border-white/10 bg-slate-900/70 p-4 text-left transition hover:border-cyan-400/60 hover:bg-slate-800" onclick="selectChat('${esc(g.chat_id)}','${esc(g.title||g.username||g.chat_id)}')"><div class="font-medium">${esc(g.title||g.username||g.chat_id)}</div><div class="mt-1 text-xs text-slate-400">${esc(g.type)} · ${esc(g.chat_id)} · ${time(g.last_seen)}</div></button>`).join('')||'<p class="text-sm text-slate-400">No groups logged yet.</p>'}
function renderMessages(messages){$('messages').innerHTML=messages.map(m=>`<article class="fade rounded-2xl border border-white/10 bg-slate-900/70 p-4 transition hover:bg-slate-800"><div class="mb-2 flex flex-wrap items-center gap-2 text-xs text-slate-400"><span>${time(m.created_at)}</span><span>${esc(m.update_type)}/${esc(m.message_type)}</span><span>chat ${esc(m.chat_id)}</span>${m.topic_id?`<span>topic ${esc(m.topic_id)}</span>`:''}${m.message_id?`<span>msg ${esc(m.message_id)}</span>`:''}</div><div class="text-sm text-cyan-200">${esc([m.first_name,m.last_name].filter(Boolean).join(' ')||m.username||m.user_id||'Unknown')}</div><p class="mt-2 whitespace-pre-wrap text-sm leading-6">${esc(m.text||'[non-text update]')}</p><button class="mt-3 rounded-xl bg-white/10 px-3 py-1 text-xs transition hover:bg-cyan-400 hover:text-slate-950" onclick="fillReply('${esc(m.chat_id)}','${esc(m.topic_id||'')}','${esc(m.message_id||'')}')">Reply</button></article>`).join('')||'<p class="text-sm text-slate-400">No messages logged yet.</p>'}
async function load(){const d=await api('?api=dashboard');$('stats').innerHTML=statCard('Groups',d.stats.groups)+statCard('Private chats',d.stats.private_chats)+statCard('Messages',d.stats.messages)+statCard('Topics',d.stats.topics);renderGroups(d.groups);renderMessages(d.messages)}
async function selectChat(id,title){selectedChat=id;$('activeChat').textContent=title;$('chat_id').value=id;const d=await api('?api=messages&chat_id='+encodeURIComponent(id));renderMessages(d.messages)}
function fillReply(chat,topic,msg){$('chat_id').value=chat;$('topic_id').value=topic;$('message_id').value=msg;document.querySelector('[name=text]').focus()}
$('refresh').onclick=()=>selectedChat?selectChat(selectedChat,$('activeChat').textContent):load();
$('replyForm').onsubmit=async e=>{e.preventDefault();$('replyStatus').textContent='Sending...';try{await api('?api=reply',{method:'POST',body:new FormData(e.target)});$('replyStatus').textContent='Sent';e.target.text.value='';setTimeout(()=>$('replyStatus').textContent='',1800)}catch(err){$('replyStatus').textContent=err.message}};
load();setInterval(()=>$('refresh').click(),8000);
</script>
</body>
</html>
