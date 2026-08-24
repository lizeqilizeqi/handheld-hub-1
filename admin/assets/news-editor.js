(function () {
  'use strict';

  var form = document.getElementById('news-publish-form');
  var editorEl = document.getElementById('news-quill-editor');
  if (!form || !editorEl || typeof Quill === 'undefined') {
    return;
  }

  var bodyField = document.getElementById('news-body-html');
  var coverUrlField = document.getElementById('news-cover-url');
  var coverPreview = document.getElementById('news-cover-preview');
  var coverFile = document.getElementById('news-cover-file');
  var bodyImageFile = document.getElementById('news-body-image-file');

  var quill = new Quill(editorEl, {
    theme: 'snow',
    modules: {
      toolbar: '#news-quill-toolbar',
    },
  });

  if (bodyField && bodyField.value) {
    quill.root.innerHTML = bodyField.value;
  }

  function uploadFile(file) {
    var fd = new FormData();
    fd.append('image', file);
    return fetch('news_upload.php', {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
    }).then(function (res) {
      return res.json();
    }).then(function (data) {
      if (!data.ok || !data.url) {
        throw new Error(data.error || '上传失败');
      }
      return data.url;
    });
  }

  function uploadDataUrl(dataUrl) {
    return fetch('news_upload.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ dataUrl: dataUrl }),
      credentials: 'same-origin',
    }).then(function (res) {
      return res.json();
    }).then(function (data) {
      if (!data.ok || !data.url) {
        throw new Error(data.error || '上传失败');
      }
      return data.url;
    });
  }

  function setCover(url) {
    if (!coverUrlField || !coverPreview) {
      return;
    }
    coverUrlField.value = url;
    coverPreview.innerHTML = url ? '<img src="' + url.replace(/"/g, '&quot;') + '" alt="">' : '<span class="muted">无封面</span>';
  }

  var coverPick = document.getElementById('news-cover-pick');
  var coverClear = document.getElementById('news-cover-clear');
  if (coverPick && coverFile) {
    coverPick.addEventListener('click', function () {
      coverFile.click();
    });
    coverFile.addEventListener('change', function () {
      if (!coverFile.files || !coverFile.files[0]) {
        return;
      }
      uploadFile(coverFile.files[0]).then(setCover).catch(function (e) {
        alert(e.message || String(e));
      });
      coverFile.value = '';
    });
  }
  if (coverClear) {
    coverClear.addEventListener('click', function () {
      setCover('');
    });
  }

  var bodyImageBtn = document.getElementById('news-body-image');
  if (bodyImageBtn && bodyImageFile) {
    bodyImageBtn.addEventListener('click', function () {
      bodyImageFile.click();
    });
    bodyImageFile.addEventListener('change', function () {
      if (!bodyImageFile.files || !bodyImageFile.files[0]) {
        return;
      }
      uploadFile(bodyImageFile.files[0]).then(function (url) {
        var range = quill.getSelection(true);
        quill.insertEmbed(range ? range.index : 0, 'image', url, 'user');
      }).catch(function (e) {
        alert(e.message || String(e));
      });
      bodyImageFile.value = '';
    });
  }

  quill.root.addEventListener('paste', function (e) {
    var items = (e.clipboardData && e.clipboardData.items) ? e.clipboardData.items : [];
    for (var i = 0; i < items.length; i++) {
      if (items[i].type && items[i].type.indexOf('image/') === 0) {
        e.preventDefault();
        var blob = items[i].getAsFile();
        if (!blob) {
          return;
        }
        uploadFile(blob).then(function (url) {
          var range = quill.getSelection(true);
          quill.insertEmbed(range ? range.index : 0, 'image', url, 'user');
        }).catch(function (err) {
          alert(err.message || String(err));
        });
        return;
      }
    }
  });

  form.addEventListener('submit', function () {
    if (bodyField) {
      bodyField.value = quill.root.innerHTML;
    }
  });

  var tip = document.getElementById('news-paste-tip');
  if (tip) {
    tip.addEventListener('click', function () {
      alert('可直接 Ctrl+V 粘贴含文字与图片的内容；剪贴板图片会自动上传到本站。');
    });
  }
})();
