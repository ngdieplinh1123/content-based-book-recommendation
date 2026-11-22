<style>
    #catBot { position: fixed; right: 18px; bottom: 18px; z-index: 9999; font-family: inherit; }
    #catBotBtn { width: 92px; height: 92px; border-radius: 50%; background: #fff; border: 3px solid #e87500; box-shadow: 0 6px 18px rgba(0,0,0,.25); cursor: pointer; padding: 0; display: flex; align-items: center; justify-content: center; animation: catBounce 2.6s ease-in-out infinite; }
    #catBotBtn:hover { transform: scale(1.06); }
    #catBotTip { position: absolute; right: 8px; bottom: 104px; background: #fff; border: 2px solid #e87500; border-radius: 16px; padding: 9px 16px; font-size: 15px; font-weight: 700; color: #7a4a10; white-space: nowrap; box-shadow: 0 6px 16px rgba(0,0,0,.18); animation: catBounce 2.6s ease-in-out infinite; }
    #catBotTip::after { content: ''; position: absolute; right: 30px; bottom: -9px; width: 14px; height: 14px; background: #fff; border-right: 2px solid #e87500; border-bottom: 2px solid #e87500; transform: rotate(45deg); }
    #catBotPanel { position: absolute; right: 0; bottom: 106px; width: 360px; max-width: calc(100vw - 28px); height: 520px; max-height: calc(100vh - 120px); background: #fff; border-radius: 14px; box-shadow: 0 14px 40px rgba(0,0,0,.28); display: none; flex-direction: column; overflow: hidden; border: 1px solid #ecd9c0; }
    #catBotPanel.open { display: flex; }
    #catBotHead { background: #e87500; color: #fff; padding: 10px 14px; display: flex; align-items: center; gap: 10px; }
    #catBotMsgs { flex: 1; overflow-y: auto; padding: 12px; background: #faf6f0; display: flex; flex-direction: column; gap: 10px; }
    .cb-msg { white-space: pre-line; max-width: 88%; padding: 8px 12px; border-radius: 12px; font-size: 14px; line-height: 1.4; word-wrap: break-word; }
    .cb-bot { background: #fff; border: 1px solid #efe3d1; align-self: flex-start; color: #333; }
    .cb-user { background: #e87500; color: #fff; align-self: flex-end; }
    .cb-prod { display: flex; gap: 10px; background: #fff; border: 1px solid #efe3d1; border-radius: 10px; padding: 6px; text-decoration: none; color: #222; align-self: stretch; }
    .cb-prod:hover { border-color: #e87500; background: #fff8ef; }
    .cb-prod img { width: 44px; height: 62px; object-fit: cover; border: 1px solid #eee; flex-shrink: 0; background: #f4f4f4; }
    .cb-chips { display: flex; flex-wrap: wrap; gap: 6px; }
    .cb-chip { border: 1px solid #e87500; color: #c65f00; background: #fff; border-radius: 999px; padding: 4px 10px; font-size: 12px; cursor: pointer; }
    .cb-chip:hover { background: #fff1e0; }
    #catBotForm { display: flex; border-top: 1px solid #eee; background: #fff; }
    #catBotInput { flex: 1; border: 0; padding: 12px; font-size: 14px; outline: none; min-width: 0; }
    #catBotForm button { border: 0; background: #e87500; color: #fff; padding: 0 16px; cursor: pointer; }
    .cb-typing span { display: inline-block; width: 6px; height: 6px; margin: 0 1px; background: #bbb; border-radius: 50%; animation: cbDot 1s infinite; }
    .cb-typing span:nth-child(2) { animation-delay: .15s; } .cb-typing span:nth-child(3) { animation-delay: .3s; }
    @keyframes cbDot { 0%, 80%, 100% { opacity: .3; transform: translateY(0); } 40% { opacity: 1; transform: translateY(-3px); } }
    @keyframes catBounce { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-4px); } }
    @media (prefers-reduced-motion: reduce) { #catBotBtn { animation: none; } }
</style>

<?php
$catSvg = '<svg viewBox="0 0 64 64" width="%1$d" height="%1$d" aria-hidden="true">
  <path d="M10 28 L12 6 L26 16 Z" fill="#f5a04a" stroke="#7a4a10" stroke-width="2" stroke-linejoin="round"/>
  <path d="M54 28 L52 6 L38 16 Z" fill="#f5a04a" stroke="#7a4a10" stroke-width="2" stroke-linejoin="round"/>
  <path d="M14 22 L15 12 L22 17 Z" fill="#ffc7c7"/><path d="M50 22 L49 12 L42 17 Z" fill="#ffc7c7"/>
  <ellipse cx="32" cy="36" rx="23" ry="20" fill="#f5a04a" stroke="#7a4a10" stroke-width="2"/>
  <path d="M26 17 Q32 22 38 17" stroke="#c9722a" stroke-width="3" fill="none" stroke-linecap="round"/>
  <ellipse cx="23" cy="34" rx="3.4" ry="4.4" fill="#2b1a0a"/><ellipse cx="41" cy="34" rx="3.4" ry="4.4" fill="#2b1a0a"/>
  <circle cx="24.2" cy="32.4" r="1.2" fill="#fff"/><circle cx="42.2" cy="32.4" r="1.2" fill="#fff"/>
  <ellipse cx="17" cy="42" rx="4" ry="2.6" fill="#ff9b9b" opacity=".6"/><ellipse cx="47" cy="42" rx="4" ry="2.6" fill="#ff9b9b" opacity=".6"/>
  <path d="M29.5 40 L34.5 40 L32 43 Z" fill="#e0607a"/>
  <path d="M32 43 Q29 47 26 45 M32 43 Q35 47 38 45" stroke="#7a4a10" stroke-width="1.8" fill="none" stroke-linecap="round"/>
  <path d="M6 38 L16 40 M6 45 L16 44 M58 38 L48 40 M58 45 L48 44" stroke="#7a4a10" stroke-width="1.4" stroke-linecap="round"/>
</svg>';
?>
<div id="catBot">
    <div id="catBotTip">Meo~ Cần tìm sách không? 🐾</div>
    <div id="catBotPanel" role="dialog" aria-label="Trợ lý Mèo CatBook">
        <div id="catBotHead">
            <div style="background:#fff;border-radius:50%;width:40px;height:40px;display:flex;align-items:center;justify-content:center"><?= sprintf($catSvg, 34) ?></div>
            <div style="flex:1"><div style="font-weight:800">Mèo <?= $siteName ?></div><div style="font-size:12px;opacity:.9">Trợ lý tìm sách</div></div>
            <button type="button" id="catBotClose" aria-label="Đóng" style="background:none;border:0;color:#fff;font-size:20px;cursor:pointer">&times;</button>
        </div>
        <div id="catBotMsgs"></div>
        <form id="catBotForm" autocomplete="off">
            <input id="catBotInput" type="text" maxlength="200" placeholder="Nhập tên sách, tác giả, thể loại, giá..." aria-label="Nhập tin nhắn">
            <button type="submit" aria-label="Gửi"><i class="fas fa-paper-plane"></i></button>
        </form>
    </div>
    <button type="button" id="catBotBtn" aria-label="Mở trợ lý Mèo CatBook"><?= sprintf($catSvg, 74) ?></button>
</div>

<script>
(function () {
    var btn = document.getElementById('catBotBtn'), panel = document.getElementById('catBotPanel'),
        tip = document.getElementById('catBotTip'), msgs = document.getElementById('catBotMsgs'),
        form = document.getElementById('catBotForm'), input = document.getElementById('catBotInput'),
        started = false, busy = false;

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c];
        });
    }
    function scroll() { msgs.scrollTop = msgs.scrollHeight; }
    function addMsg(text, who) {
        var d = document.createElement('div');
        d.className = 'cb-msg ' + (who === 'user' ? 'cb-user' : 'cb-bot');
        d.textContent = text;
        msgs.appendChild(d); scroll();
    }
    function render(data) {
        addMsg(data.reply, 'bot');
        (data.products || []).forEach(function (p) {
            var a = document.createElement('a');
            a.className = 'cb-prod';
            a.href = 'product_detail.php?id=' + p.id;
            a.innerHTML = '<img src="' + esc(p.image) + '" alt="">' +
                '<div style="min-width:0;flex:1"><div style="font-size:13px;font-weight:600;line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden">' + esc(p.name) + '</div>' +
                (p.author ? '<div style="font-size:12px;color:#777">' + esc(p.author) + '</div>' : '') +
                '<div style="font-size:13px;font-weight:700;color:#e87500">' + esc(p.price) +
                (p.old_price ? ' <span style="font-weight:400;color:#999;text-decoration:line-through;font-size:12px">' + esc(p.old_price) + '</span>' : '') + '</div></div>';
            msgs.appendChild(a);
        });
        if (data.chips && data.chips.length) {
            var c = document.createElement('div');
            c.className = 'cb-chips';
            data.chips.forEach(function (t) {
                var b = document.createElement('button');
                b.type = 'button'; b.className = 'cb-chip'; b.textContent = t;
                b.addEventListener('click', function () { send(t); });
                c.appendChild(b);
            });
            msgs.appendChild(c);
        }
        scroll();
    }
    function send(text) {
        text = (text || '').trim();
        if (busy) return;
        if (text) addMsg(text, 'user');
        busy = true;
        var typing = document.createElement('div');
        typing.className = 'cb-msg cb-bot cb-typing';
        typing.innerHTML = '<span></span><span></span><span></span>';
        msgs.appendChild(typing); scroll();
        var body = new URLSearchParams(); body.set('message', text);
        fetch('chatbot.php', {method: 'POST', body: body})
            .then(function (r) { return r.json(); })
            .then(function (d) { typing.remove(); render(d); })
            .catch(function () { typing.remove(); addMsg('Meo… mình đang gặp sự cố, bạn thử lại sau nhé.', 'bot'); })
            .finally(function () { busy = false; });
    }
    function toggle(open) {
        panel.classList.toggle('open', open);
        tip.style.display = 'none';
        if (open) {
            if (!started) { started = true; send(''); }
            setTimeout(function () { input.focus(); }, 50);
        }
    }
    btn.addEventListener('click', function () { toggle(!panel.classList.contains('open')); });
    document.getElementById('catBotClose').addEventListener('click', function () { toggle(false); });
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var v = input.value; input.value = '';
        if (v.trim()) send(v);
    });
    setTimeout(function () { tip.style.display = 'none'; }, 8000);
})();
</script>

