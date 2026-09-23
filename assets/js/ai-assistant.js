(function () {
  var root = document.getElementById('ai-assist');
  if (!root) return;
  var endpoint = root.getAttribute('data-endpoint');
  var prefix = root.getAttribute('data-prefix') || '';
  var fab = root.querySelector('.ai-fab');
  var panel = root.querySelector('.ai-panel');
  var log = root.querySelector('.ai-log');
  var form = root.querySelector('.ai-input');
  var input = form.querySelector('textarea');
  var sendBtn = form.querySelector('button');
  var STORE = 'hruform_ai_chat';
  var history = [];
  try { history = JSON.parse(sessionStorage.getItem(STORE) || '[]') || []; } catch (e) { history = []; }

  function save() { try { sessionStorage.setItem(STORE, JSON.stringify(history.slice(-30))); } catch (e) {} }

  function esc(s) {
    return s.replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
  }

  function fmt(text) {
    return esc(text)
      .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
      .replace(/`([^`\n]+)`/g, '<code>$1</code>')
      .replace(/\n/g, '<br>');
  }

  function extractDraft(text) {
    var m = text.match(/```(?:json)?\s*([\s\S]*?)```/);
    if (!m) return null;
    try {
      var d = JSON.parse(m[1]);
      if (d && Array.isArray(d.questions) && d.questions.length) return { draft: d, rest: text.replace(m[0], '').trim() };
    } catch (e) {}
    return null;
  }

  var TYPE_LABEL = { mc: 'เลือกข้อเดียว', checkbox: 'หลายข้อ', dropdown: 'รายการ', scale: 'ระดับความเห็น', short: 'ปลายเปิด' };

  function draftCard(d) {
    var card = document.createElement('div');
    card.className = 'ai-draft';
    var html = '<div class="ai-draft-title">' + esc(String(d.title || 'ร่างแบบฟอร์ม')) + '</div><ol>';
    d.questions.forEach(function (q) {
      html += '<li><span class="ai-draft-type">' + esc(TYPE_LABEL[q.type] || String(q.type || 'mc')) + '</span> ' + esc(String(q.text || ''));
      if (Array.isArray(q.options) && q.options.length) {
        html += '<ul>' + q.options.map(function (o) {
          var label = typeof o === 'object' && o ? String(o.label || '') : String(o);
          return '<li' + (o && o.is_correct ? ' class="ok"' : '') + '>' + esc(label) + '</li>';
        }).join('') + '</ul>';
      }
      html += '</li>';
    });
    html += '</ol>';
    card.innerHTML = html;
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-primary ai-draft-btn';
    btn.textContent = 'สร้างฟอร์มจากร่างนี้ (' + d.questions.length + ' ข้อ)';
    btn.addEventListener('click', function () {
      btn.disabled = true;
      btn.textContent = 'กำลังสร้าง...';
      post({ action: 'create_form', draft: d }).then(function (res) {
        location.href = prefix + res.url;
      }).catch(function (err) {
        btn.disabled = false;
        btn.textContent = 'ลองอีกครั้ง';
        addBubble('error', err.message);
      });
    });
    card.appendChild(btn);
    return card;
  }

  function addBubble(role, text) {
    var el = document.createElement('div');
    el.className = 'ai-msg ai-' + role;
    if (role === 'assistant') {
      var parsed = extractDraft(text);
      if (parsed) {
        if (parsed.rest) { var p = document.createElement('div'); p.innerHTML = fmt(parsed.rest); el.appendChild(p); }
        el.appendChild(draftCard(parsed.draft));
      } else {
        el.innerHTML = fmt(text);
      }
    } else {
      el.innerHTML = fmt(text);
    }
    log.appendChild(el);
    log.scrollTop = log.scrollHeight;
    return el;
  }

  function post(body) {
    return fetch(endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(body)
    }).then(function (r) {
      return r.json().catch(function () { return { error: 'เซิร์ฟเวอร์ตอบกลับไม่ถูกต้อง' }; }).then(function (j) {
        if (!r.ok || j.error) throw new Error(j.error || ('HTTP ' + r.status));
        return j;
      });
    });
  }

  function render() {
    log.innerHTML = '';
    if (!history.length) {
      var w = document.createElement('div');
      w.className = 'ai-welcome';
      w.innerHTML = 'สวัสดีครับ 👋 ผมช่วยร่างแบบสอบถามหรือแบบทดสอบให้ได้<div class="ai-chips"></div>';
      ['ร่างแบบสอบถามความพึงพอใจการอบรม 8 ข้อ', 'ร่างแบบทดสอบคณิตศาสตร์ ม.1 เรื่องเศษส่วน 10 ข้อ', 'ช่วยคิดคำถามปลายเปิดสำหรับประเมินกิจกรรม'].forEach(function (s) {
        var c = document.createElement('button');
        c.type = 'button';
        c.className = 'ai-chip';
        c.textContent = s;
        c.addEventListener('click', function () { send(s); });
        w.querySelector('.ai-chips').appendChild(c);
      });
      log.appendChild(w);
    }
    history.forEach(function (m) { addBubble(m.role, m.content); });
  }

  var busy = false;
  function send(text) {
    text = (text || '').trim();
    if (!text || busy) return;
    if (!history.length) log.innerHTML = '';
    busy = true;
    sendBtn.disabled = true;
    history.push({ role: 'user', content: text });
    save();
    addBubble('user', text);
    var typing = addBubble('typing', '');
    typing.innerHTML = '<span></span><span></span><span></span>';
    post({ messages: history }).then(function (res) {
      history.push({ role: 'assistant', content: res.reply });
      save();
      typing.remove();
      addBubble('assistant', res.reply);
    }).catch(function (err) {
      typing.remove();
      history.pop(); // drop the unanswered user turn so it can be retried
      save();
      addBubble('error', err.message);
      input.value = text;
    }).then(function () {
      busy = false;
      sendBtn.disabled = false;
      input.focus();
    });
  }

  fab.addEventListener('click', function () {
    var open = root.classList.toggle('open');
    fab.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) { if (!log.childElementCount) render(); input.focus(); }
  });
  root.querySelector('.ai-close').addEventListener('click', function () { root.classList.remove('open'); fab.setAttribute('aria-expanded', 'false'); });
  root.querySelector('.ai-reset').addEventListener('click', function () { history = []; save(); render(); });
  form.addEventListener('submit', function (e) { e.preventDefault(); var t = input.value; input.value = ''; send(t); });
  input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit')); }
  });
})();
