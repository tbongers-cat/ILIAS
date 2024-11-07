il.Awareness = {

  rendered: false,
  base_url: '',
  $body: $(document.body),
  loader_src: '',

  setBaseUrl(url) {
    const t = il.Awareness;
    t.base_url = url;
  },

  getBaseUrl() {
    const t = il.Awareness;
    return t.base_url;
  },

  setLoaderSrc(loader) {
    const t = il.Awareness;
    t.loader_src = loader;
  },

  getLoaderSrc() {
    const t = il.Awareness;
    return t.loader_src;
  },

  init() {
    console.log('**************************INIT***********************');
    document.querySelectorAll('.ilAwarenessItem').forEach((el) => {
      el.addEventListener('click', () => {
        const ul = el.querySelector('ul');
        if (ul.style.display === 'none') {
          ul.style.display === 'block';
        } else {
          ul.style.display === 'none';
        }
      });
    });
  },

  getContent() {
    const t = il.Awareness;
    if (!t.rendered) {
      t.content = $('#awareness-content-container').html();
      $('#awareness-content-container').html('');
      t.updateList('');
      t.rendered = true;
    }
    return t.content;
  },

  ajaxReplaceSuccess(o) {
    const t = il.Awareness; let
      cnt;

    // perform page modification
    if (o.html !== undefined) {
      t.content = o.html;
      $('#awareness-content').replaceWith(o.html);
      $('#il_awareness_filter').val(o.filter_val);
      t.afterListUpdate();

      cnt = o.cnt.split(':');
      t.setCounter(cnt[0], false);
      t.setCounter(cnt[1], true);

      // throw custom event
      $('#awareness_trigger a').trigger('awrn:shown');
    }
  },

  afterListUpdate() {
    let t = il.Awareness;

    $('#il_awrn_filter_form').submit((e) => {
      const t = il.Awareness;
      $('#il_awrn_filer_btn').html(`<img src='${t.loader_src}' />`);
      t.updateList($('#il_awareness_filter').val());
      e.preventDefault();
    });
    $('#il_awareness_filter').each(function () {
      t = this;
      t.focus();
      if (t.setSelectionRange) {
        const len = $(t).val().length * 2;
        t.setSelectionRange(len, len);
      }
    });

    $('#awareness-list').trigger('il.user.actions.updated', ['awareness-list']);
  },

  updateList(filter) {
    const t = il.Awareness;
    $.ajax({
      url: `${t.getBaseUrl()}&cmd=getAwarenessList`
				+ `&filter=${encodeURIComponent(filter)}`,
      dataType: 'json',
    }).done(t.ajaxReplaceSuccess);
  },

  setCounter(c, highlighted) {
    let id = '#awareness_badge';

    if (highlighted) {
      id = '#awareness_hbadge';
    }
    $(`${id} span`).html(c);
    if (c > 0) {
      $(`${id} .badge`).removeClass('ilAwrnBadgeHidden');
    } else {
      $(`${id} .badge`).addClass('ilAwrnBadgeHidden');
    }
  },
};

/* temporary fix, since initial ajax loading does not work */
il.Util.addOnLoad(() => {
  il.Awareness.afterListUpdate();
});
