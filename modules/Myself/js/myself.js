/**
 * Myself Module Class
 */
class Myself {

  /**
   * Custom fonts added in theme settings
   * @type {Object<string, *>}
   */
  static customFonts = {}

  /**
   * Add classes that can be position:sticky
   * This help to provide correct jump marker with a correct offset
   * @type {string[]}
   */
  static possibleStickyClasses = []

  /**
   * Default fonts available
   * @type {Object<string, *>}
   */
  static defaultFonts = {
    'Andale Mono': { 'name': 'andale mono,times,sans-serif' },
    'Arial': { 'name': 'arial,helvetica,sans-serif' },
    'Arial Black': { 'name': 'arial black,avant garde,sans-serif' },
    'Book Antiqua': { 'name': 'book antiqua,palatino,sans-serif' },
    'Comic Sans MS': { 'name': 'comic sans ms,sans-serif' },
    'Courier New': { 'name': 'courier new,courier,sans-serif' },
    'Georgia': { 'name': 'georgia,palatino,sans-serif' },
    'Helvetica': { 'name': 'helvetica,sans-serif' },
    'Impact': { 'name': 'impact,chicago,sans-serif' },
    'Symbol': { 'name': 'symbol,sans-serif' },
    'Tahoma': { 'name': 'tahoma,arial,helvetica,sans-serif' },
    'Terminal': { 'name': 'terminal,monaco,sans-serif' },
    'Times New Roman': { 'name': 'times new roman,times,sans-serif' },
    'Trebuchet MS': { 'name': 'trebuchet ms,geneva,sans-serif' },
    'Verdana': { 'name': 'verdana,geneva,sans-serif' },
    'Webdings': { 'name': 'webdings' },
    'Wingdings': { 'name': 'wingdings,zapf dingbats' }
  }

  /**
   * Parse custom fonts out of settings value and add it to the dom
   * @param {string} settingsValue
   */
  static parseCustomFonts (settingsValue) {
    const urls = (settingsValue || '').match(/https:\/\/fonts.googleapis.com\/css2([^"'\s]+)/gi)
    Myself.customFonts = {}
    if (urls) {
      for (let i = 0; i < urls.length; i++) {
        const url = urls[i]
        const families = url.match(/family=[^&]+/ig)
        if (families) {
          for (let j = 0; j < families.length; j++) {
            const family = families[j].substr(7).split(':')
            Myself.customFonts[family[0]] = {
              'url': 'https://fonts.googleapis.com/css2?family=' + family.join(':') + '&display=swap',
              'includeParam': family.join(':'),
              'name': decodeURIComponent(family[0].replace(/\+/g, ' '))
            }
          }
        }
      }
    }
    if (FramelixObjectUtils.hasKeys(Myself.customFonts)) {
      const head = $('head')
      head.append('<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>')
      let url = 'https://fonts.googleapis.com/css2?'
      for (let key in Myself.customFonts) {
        const row = Myself.customFonts[key]
        url += '&family=' + row.includeParam
      }
      url += '&display=swap'
      head.append('<link href="' + url + '" rel="stylesheet">')
    }
  }

  /**
   * Is page in edit mode (The outer frame)
   * @return {boolean}
   */
  static isEditModeOuter () {
    return $('html').attr('data-edit') === '1'
  }

  /**
   * Is page in edit mode (The inner frame)
   * @return {boolean}
   */
  static isEditModeInner () {
    return window.top !== window ? window.top.document.querySelector('html').getAttribute('data-edit') === '1' : false
  }

  /**
   * Init late
   */
  static initLate () {
    FramelixDom.addChangeListener('myself-dom', function () {
      Myself.onDomChange()
    })
    Myself.onDomChange()
    // remember edit mode for this device to always show a quick enable edit mode button on the left corner
    if (Myself.isEditModeOuter() && !FramelixLocalStorage.get('myself-edit-mode')) {
      FramelixLocalStorage.set('myself-edit-mode', true)
    }
    if (FramelixLocalStorage.get('myself-edit-mode') && !Myself.isEditModeOuter() && $('.framelix-page').length) {
      const editModeContainer = $(`<div class="myself-open-edit-mode myself-hide-if-editmode"><button class="framelix-button" data-icon-left="clear" title="__myself_hide_editmode_container__"></button> <a href="?editMode=1" class="framelix-button framelix-button-primary" title="__myself_enable_editmode__" data-icon-left="edit"></a></div>`)
      editModeContainer.on('click', 'button', function () {
        editModeContainer.remove()
      })
      $('.framelix-page').after(editModeContainer)
    }
    window.addEventListener('hashchange', function () {
      Myself.onHashChange()

    }, false)
    Myself.onHashChange()
  }

  /**
   * On hash change
   */
  static onHashChange () {
    if (!window.location.hash.startsWith('#jumpmark-')) return
    const target = $('#' + window.location.hash.substr(10))
    if (!target.length) return
    let offset = 0
    for (let i = 0; i < Myself.possibleStickyClasses.length; i++) {
      const cl = Myself.possibleStickyClasses[i]
      const el = $('.' + cl).first()
      if (!el.length) continue
      const style = window.getComputedStyle(el[0])
      if (style.position === 'sticky' || style.position === 'fixed') {
        offset += parseInt(style.height.replace(/[^0-9]/g, ''))
        Framelix.scrollTo(target, null, offset)
        break
      }
    }
  }

  /**
   * On dom change
   */
  static onDomChange () {
    $('.myself-block-layout-row[data-background-video], .myself-block-layout-row-column[data-background-video]').each(function () {
      const el = $(this)
      const backgroundVideo = el.attr('data-background-video')
      el.removeAttr('data-background-video')
      el.attr('data-background-video-original', backgroundVideo)
      FramelixIntersectionObserver.onGetVisible(this, function () {
        function updateVideoPosition () {
          const elWidth = el.width()
          const elHeight = el.height()
          const wRatio = 1 / video.videoWidth * elWidth
          const hRatio = 1 / video.videoHeight * elHeight
          const minRatio = Math.min(wRatio, hRatio)
          const maxRatio = Math.max(wRatio, hRatio)
          video.width = video.videoWidth * minRatio
          video.height = video.videoHeight * minRatio
          if (backgroundSize === 'cover') {
            video.width = video.videoWidth * maxRatio
            video.height = video.videoHeight * maxRatio
          }
          video.style.left = (elWidth / 2 - video.width / 2) + 'px'
          video.style.top = (elHeight / 2 - video.height / 2) + 'px'
          if (backgroundPosition === 'top') {
            video.style.top = '0px'
          } else if (backgroundPosition === 'bottom') {
            video.style.top = elHeight + 'px'
          }
        }

        /** @type {HTMLVideoElement} */
        const video = document.createElement('video')
        video.autoplay = true
        video.loop = true
        video.muted = true
        video.src = backgroundVideo
        video.poster = el.attr('data-background-image') || el.attr('data-background-original') || ''
        el.prepend(video)
        el.addClass('myself-block-layout-background-video')
        video.play()
        const backgroundSize = el.attr('data-background-size') || 'cover'
        const backgroundPosition = el.attr('data-background-position') || 'center'
        video.addEventListener('timeupdate', updateVideoPosition)
        video.addEventListener('play', updateVideoPosition)
        updateVideoPosition()
      })
    })
    $('.myself-block-layout-row[data-background-image], .myself-block-layout-row-column[data-background-image]').each(function () {
      const el = $(this)
      const backgroundImage = el.attr('data-background-image')
      const backgroundPosition = el.attr('data-background-position') || 'center'
      el.removeAttr('data-background-image')
      el.attr('data-background-image-original', backgroundImage)
      FramelixIntersectionObserver.onGetVisible(this, function () {
        if (!el.attr('data-background-video') && !el.attr('data-background-video-original')) {
          el.css('background-image', 'url(' + backgroundImage + ')')
          el.css('background-position', 'center ' + backgroundPosition)
        }
      })
    })
    $('.myself-lazy-load').not('.myself-lazy-load-initialized').addClass('myself-lazy-load-initialized').each(function () {
      const el = $(this)
      const imgAttr = el.attr('data-img')
      if (imgAttr) {
        const parentWidth = el.closest('.myself-lazy-load-parent-anchor').width()
        const images = imgAttr.split(';')
        let useSrc = ''
        for (let i = 0; i < images.length; i++) {
          const img = images[i].split('|')
          if (img.length > 1) {
            useSrc = img[2]
            // as soon as we have reached the container size
            if (parentWidth <= parseInt(img[0])) {
              break
            }
          } else if (img.length <= 1 && useSrc === '') {
            useSrc = img[0]
          }
        }
        // no matched image, use the one without dimension
        if (!useSrc) {
          for (let i = 0; i < images.length; i++) {
            const img = images[i].split('|')
            if (img.length <= 1 && useSrc === '') {
              useSrc = img[0]
              break
            }
          }
        }
        el.attr('data-img-src', useSrc)
      }
      FramelixIntersectionObserver.onGetVisible(this, function () {
        const imgAttr = el.attr('data-img-src')
        if (imgAttr) {
          const img = $('<img src="' + imgAttr + '">').attr('alt', el.attr('data-alt'))
          el.replaceWith(img)
        }
        const videoAttr = el.attr('data-video')
        if (videoAttr) {
          const video = $('<video src="' + videoAttr + '" loop autoplay muted></video>')
          video.attr('poster', el.attr('data-poster'))
          el.replaceWith(video)
        }
      })
    })
    // fade in / fade out
    $('.myself-block-layout-row-column[data-fade-in]').each(function () {
      const el = $(this)
      const fadeIn = el.attr('data-fade-in')
      el.removeAttr('data-fade-in')
      el.attr('data-fade-in-original', fadeIn)
      FramelixIntersectionObserver.onGetVisible(this, function () {
        el.attr('data-fade-in-active', fadeIn)
        if (el.attr('data-fade-out') === '1') {
          FramelixIntersectionObserver.onGetInvisible(el[0], function () {
            el.attr('data-fade-in', fadeIn)
            el.removeAttr('data-fade-in-original')
            el.removeAttr('data-fade-in-active')
          })
        }
      })
    })
  }
}

FramelixInit.late.push(Myself.initLate)