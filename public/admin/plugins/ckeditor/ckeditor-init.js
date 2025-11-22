(function(){
  function createUploadPlugin(uploadUrl, csrfToken, fieldName){
    return function(editor){
      if (!editor.plugins || !editor.plugins.get) return;
      var repo = editor.plugins.get('FileRepository');
      if (!repo) return;
      repo.createUploadAdapter = function(loader){
        var controller = new AbortController();
        return {
          upload: function(){
            return loader.file.then(function(file){
              return new Promise(function(resolve, reject){
                // Client-side guards
                var allowed = ['image/jpeg','image/png','image/webp','image/avif'];
                if (allowed.indexOf(file.type) === -1) { reject('نوع فایل مجاز نیست'); return; }
                var maxBytes = 4 * 1024 * 1024; // 4MB
                if (file.size > maxBytes) { reject('حجم فایل بیش از حد مجاز است'); return; }
                var fd = new FormData();
                fd.append('upload', file, file.name);
                fd.append('context', fieldName || 'editor');
                fetch(uploadUrl, {
                  method: 'POST',
                  body: fd,
                  headers: csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {},
                  signal: controller.signal,
                  credentials: 'same-origin'
                }).then(function(resp){ return resp.json().then(function(j){ return { ok: resp.ok, j: j }; }); })
                  .then(function(res){
                    if (!res.ok || !res.j) { reject('Upload failed'); return; }
                    var j = res.j;
                    var url = j.url || (j.urls && (j.urls.default || j.urls.webp || j.urls.avif));
                    if (!url) { reject('No URL returned'); return; }
                    resolve({ default: url });
                  }).catch(function(err){ reject(err && err.message ? err.message : 'Upload error'); });
              });
            });
          },
          abort: function(){ try { controller.abort(); } catch(e){} }
        };
      };
    };
  }

  function baseToolbar(isShort){
    if (isShort) return [
      'bold','italic','underline','link','|','bulletedList','numberedList','|','undo','redo'
    ];
    return [
      'heading','|','bold','italic','underline','strikethrough','link','blockQuote','|','bulletedList','numberedList','|','insertTable','uploadImage','mediaEmbed','htmlEmbed','|','undo','redo'
    ];
  }

  function buildConfig(el){
    var isShort = (el.getAttribute('name')||'') === 'short_desc';
    var csrf = (document.querySelector('meta[name="csrf-token"]')||{}).content || '';
    var uploadUrl = el.dataset.uploadUrl || (window.RMS && RMS.editorUploadUrl) || '';
    var cfg = {
      language: { ui: 'fa', content: 'fa' },
      toolbar: baseToolbar(isShort),
      heading: {
        options: [
          { model: 'paragraph', title: 'پاراگراف', class: 'ck-heading_paragraph' },
          { model: 'heading2', view: 'h2', title: 'تیتر ۲', class: 'ck-heading_heading2' },
          { model: 'heading3', view: 'h3', title: 'تیتر ۳', class: 'ck-heading_heading3' }
        ]
      },
      link: {
        addTargetToExternalLinks: true,
        defaultProtocol: 'https://',
        decorators: {
          addRelNoopener: {
            mode: 'automatic',
            callback: function( url ){ return /^(?:(?:https?:)?\/\/)/i.test(url); },
            attributes: { rel: 'noopener noreferrer nofollow' }
          }
        }
      },
      image: {
        toolbar: [ 'imageStyle:inline', 'imageStyle:block', 'imageStyle:side', '|', 'toggleImageCaption', 'imageTextAlternative', '|', 'resizeImage' ],
        upload: { types: [ 'jpeg','jpg','png','webp','avif' ] }
      },
      htmlSupport: {
        allow: [
          { name: /^.*$/, attributes: [ 'dir','lang' ], classes: [] },
          { name: 'a', attributes: [ 'href','target','rel' ], classes: [] },
          { name: 'img', attributes: [ 'src','alt','width','height' ], classes: [] },
          { name: 'video', attributes: [ 'controls','preload','poster','src','width','height','style','data-hls','data-playlist' ], classes: true, styles: true },
          { name: 'source', attributes: [ 'src','type' ], classes: false, styles: false },
          { name: 'iframe', attributes: [ 'src','width','height','frameborder','allow','allowfullscreen','loading','referrerpolicy','title' ], classes: true, styles: true },
          { name: /^(p|h[1-6]|ul|ol|li|blockquote|strong|em|u|s|code|pre|table|thead|tbody|tr|th|td|figure|figcaption)$/ }
        ],
        disallow: [ { name: /^.*$/, attributes: [ 'style', /^on.*/ ] } ]
      },
      htmlEmbed: {
        showPreviews: true,
        sanitizeHtml: function(inputHtml){
          // Allow iframes (e.g., YouTube), strip scripts for safety
          var cleaned = String(inputHtml || '').replace(/<script[\s\S]*?>[\s\S]*?<\/script>/gi, '');
          return { html: cleaned, hasChanged: cleaned !== inputHtml };
        }
      },
      mediaEmbed: {
        previewsInData: true
      },
      extraPlugins: []
    };
    if (uploadUrl) cfg.extraPlugins.push(createUploadPlugin(uploadUrl, csrf, el.getAttribute('name') || 'editor'));
    return cfg;
  }

  function initOne(el){
    if (!window.ClassicEditor || !el || el.dataset.ckeditorInitialized=='1') return;
    var config = buildConfig(el);
    ClassicEditor.create(el, config).then(function(editor){
      el.dataset.ckeditorInitialized = '1';
      editor.model.document.on('change:data', function(){ el.value = editor.getData(); });
      try {
        var editableEl = editor && editor.ui && editor.ui.view && editor.ui.view.editable && editor.ui.view.editable.element;
        var minH = el && el.dataset ? el.dataset.minHeight : null;
        function applyMinHeight(){ if (editableEl && minH){ editableEl.style.setProperty('--rms-ck-min-height', minH); editableEl.style.minHeight = minH; editableEl.style.height = 'auto'; } }
        applyMinHeight();
        if (editableEl){
          editableEl.addEventListener('focus', applyMinHeight, true);
          editableEl.addEventListener('input', applyMinHeight, true);
        }
        setTimeout(applyMinHeight, 0);
      } catch(err) { /* ignore */ }

      // Auto-convert pasted .m3u8 links OR <video> HTML to proper embed
      try {
        var clipboard = editor.plugins.get('ClipboardPipeline');
        if (clipboard) {
          clipboard.on('inputTransformation', function(evt, data){
            try {
              var plain = data && data.dataTransfer ? (data.dataTransfer.getData('text/plain') || '') : '';
              var html  = data && data.dataTransfer ? (data.dataTransfer.getData('text/html') || '') : '';
              var targetHtml = '';
              if (plain && /\.m3u8(\?.*)?$/i.test(plain.trim())){
                targetHtml = '<video controls preload="metadata" style="width:100%"><source src="'+plain.trim()+'" type="application/vnd.apple.mpegurl"></video>';
              } else if (html && /<\s*video[\s\S]*?>[\s\S]*?<\s*\/\s*video\s*>/i.test(html)) {
                // Sanitize basic video markup and keep only video+source
                var match = html.match(/<\s*video[\s\S]*?>[\s\S]*?<\s*\/\s*video\s*>/i);
                if (match && match[0]) { targetHtml = match[0]; }
              }
              if (targetHtml){
                evt.stop();
                editor.model.change(function(writer){
                  var viewFragment = editor.data.processor.toView(targetHtml);
                  var modelFragment = editor.data.toModel(viewFragment);
                  editor.model.insertContent(modelFragment, editor.model.document.selection);
                });
              }
            } catch(_){ }
          });
        }
      } catch(_){ }
    }).catch(function(e){ console.error('CKEditor init failed', e); });
  }
  function ensureGlobalStyles(){
    if (document.getElementById('rms-ckeditor-style')) return;
    var s = document.createElement('style');
    s.id = 'rms-ckeditor-style';
    s.textContent = '.ck-editor__editable,.ck-editor__editable_inline{ min-height: var(--rms-ck-min-height, 200px) !important; }';
    document.head.appendChild(s);
  }
  function scan(){
    ensureGlobalStyles();
    document.querySelectorAll('textarea.js-ckeditor,[data-editor="ckeditor"]').forEach(initOne);
  }
  document.addEventListener('DOMContentLoaded', scan);
  document.addEventListener('rms:ckeditor:scan', scan);
  window.rmsInitCkEditors = scan;
})();
