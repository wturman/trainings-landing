/**
 * Minimal WYSIWYG for admin news content — outputs <p>, <h2>, <h3>, lists, inline tags for JSON.
 */
(function () {
  const form = document.getElementById('news-admin-form');
  const editor = document.getElementById('content-editor');
  const textarea = document.getElementById('content');
  const initialJson = document.getElementById('admin-content-initial');

  if (!form || !editor || !textarea) {
    return;
  }

  function loadInitialContent() {
    let initial = '';
    if (initialJson && initialJson.textContent) {
      try {
        initial = JSON.parse(initialJson.textContent);
      } catch (e) {
        initial = '';
      }
    }
    if (typeof initial === 'string' && initial.trim() !== '') {
      editor.innerHTML = initial;
      return;
    }
    editor.innerHTML = '<p><br></p>';
  }

  function collapseWhitespace(text) {
    return text.replace(/\s+/g, ' ').trim();
  }

  function serializeInlineHtml(html) {
    return html.replace(/\r\n|\r|\n|\t/g, ' ').replace(/>\s+</g, '><').trim();
  }

  function blockIsEmpty(el) {
    const clone = el.cloneNode(true);
    clone.querySelectorAll('br').forEach(function (br) {
      br.remove();
    });
    return collapseWhitespace(clone.textContent || '') === '';
  }

  function serializeBlockElement(el) {
    const tag = el.tagName.toLowerCase();
    if (tag === 'ul' || tag === 'ol') {
      return '<' + tag + '>' + el.innerHTML.replace(/\r\n|\r|\n|\t/g, ' ').replace(/>\s+</g, '><') + '</' + tag + '>';
    }
    if (tag === 'p') {
      if (blockIsEmpty(el)) {
        return '';
      }
      const cls = el.getAttribute('class');
      const classAttr = cls ? ' class="' + cls.replace(/"/g, '&quot;') + '"' : '';
      return '<p' + classAttr + '>' + serializeInlineHtml(el.innerHTML) + '</p>';
    }
    if (tag === 'h2' || tag === 'h3') {
      if (blockIsEmpty(el)) {
        return '';
      }
      return '<' + tag + '>' + serializeInlineHtml(el.innerHTML) + '</' + tag + '>';
    }
    if (tag === 'div') {
      if (blockIsEmpty(el)) {
        return '';
      }
      return '<p>' + serializeInlineHtml(el.innerHTML) + '</p>';
    }
    return serializeInlineHtml(el.outerHTML);
  }

  function serializeEditor() {
    const parts = [];
    editor.childNodes.forEach(function (node) {
      if (node.nodeType === Node.TEXT_NODE) {
        const text = collapseWhitespace(node.textContent || '');
        if (text !== '') {
          parts.push('<p>' + text.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</p>');
        }
        return;
      }
      if (node.nodeType !== Node.ELEMENT_NODE) {
        return;
      }
      const chunk = serializeBlockElement(node);
      if (chunk !== '') {
        parts.push(chunk);
      }
    });
    return parts.join('');
  }

  function focusEditor() {
    editor.focus();
  }

  function runCommand(command, value) {
    focusEditor();
    try {
      document.execCommand(command, false, value);
    } catch (e) {
      /* ignore */
    }
  }

  function normalizeLinkTargets() {
    editor.querySelectorAll('a[href]').forEach(function (anchor) {
      anchor.setAttribute('target', '_blank');
      anchor.setAttribute('rel', 'noopener');
    });
  }

  function insertLink() {
    const selection = window.getSelection();
    if (!selection || selection.rangeCount === 0 || selection.isCollapsed) {
      window.alert('Спочатку виділіть текст для посилання.');
      return;
    }
    const url = window.prompt('URL посилання:', 'https://');
    if (url === null) {
      return;
    }
    const href = url.trim();
    if (href === '') {
      return;
    }
    focusEditor();
    const range = selection.getRangeAt(0);
    const anchor = document.createElement('a');
    anchor.href = href;
    anchor.target = '_blank';
    anchor.rel = 'noopener';
    anchor.appendChild(range.extractContents());
    range.insertNode(anchor);
    selection.removeAllRanges();
    const after = document.createRange();
    after.setStartAfter(anchor);
    after.collapse(true);
    selection.addRange(after);
  }

  function handleEnter(event) {
    if (event.key !== 'Enter' || event.shiftKey) {
      return;
    }
    event.preventDefault();
    runCommand('formatBlock', 'p');
  }

  const toolbar = document.querySelector('[data-admin-content-toolbar]');
  if (toolbar) {
    toolbar.addEventListener('click', function (event) {
      const button = event.target.closest('[data-editor-cmd]');
      if (!button) {
        return;
      }
      event.preventDefault();
      const cmd = button.getAttribute('data-editor-cmd');
      if (cmd === 'link') {
        insertLink();
        return;
      }
      if (cmd === 'unlink') {
        runCommand('unlink');
        return;
      }
      if (cmd === 'bold' || cmd === 'italic' || cmd === 'ul' || cmd === 'ol') {
        const map = {
          bold: 'bold',
          italic: 'italic',
          ul: 'insertUnorderedList',
          ol: 'insertOrderedList',
        };
        runCommand(map[cmd]);
        return;
      }
      if (cmd === 'h2' || cmd === 'h3') {
        runCommand('formatBlock', cmd);
      }
    });
  }

  editor.addEventListener('keydown', handleEnter);
  editor.addEventListener('input', normalizeLinkTargets);
  editor.addEventListener('blur', normalizeLinkTargets);

  form.addEventListener('submit', function (event) {
    normalizeLinkTargets();
    const html = serializeEditor();
    textarea.value = html;
    const plain = html.replace(/<[^>]+>/g, '').replace(/\s+/g, ' ').trim();
    if (plain === '') {
      event.preventDefault();
      window.alert('Контент обовʼязковий.');
      editor.focus();
    }
  });

  loadInitialContent();
})();
