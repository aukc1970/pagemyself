class MyselfPageBlocksNavigation extends MyselfPageBlocks {
  /**
   * Init block
   */
  initBlock () {
    const nav = this.blockContainer.find('nav').first()
    const navList = nav.find('.myself-pageblocks-navigation-navlist').first()
    if (!navList.length) return
    const activeLinks = navList.find('.myself-pageblocks-navigation-active-link')
    const layout = nav.attr('data-layout')
    nav.parent().attr('data-layout', layout)
    if (layout === 'horizontal') {
      const groupConfigMap = new Map()
      $(document).off('click.navigation').on('click.navigation', '.myself-pageblocks-navigation-navlist-group, .myself-pageblocks-navigation-more', function () {
        let config = groupConfigMap.get(this)
        if (!config) {
          config = {}
          groupConfigMap.set(this, config)
        }
        const el = $(this)
        if (config.popup) return
        let popupContent
        if (el.hasClass('myself-pageblocks-navigation-more')) {
          popupContent = navList.clone()
          popupContent.find('.myself-pageblocks-navigation-more').remove()
          popupContent.find('.myself-pageblocks-navigation-navlist-logo').remove()
          popupContent.find('li').removeClass('hidden')
        } else {
          popupContent = el.next('ul').clone()
        }
        popupContent.addClass('myself-pageblocks-navigation-popup')
        config.popup = FramelixPopup.show(el, popupContent, {
          placement: parseInt(popupContent.attr('data-level')) <= 1 ? 'bottom' : 'left',
          color: '#fff'
        })
        config.popup.destroyed.then(function () {
          config.popup = null
        })
      })
      const lis = nav.children('ul').children('li').not('.myself-pageblocks-navigation-more').not('.myself-pageblocks-navigation-navlist-logo')
      const containerBoundingRect = nav[0].getBoundingClientRect()
      lis.each(function () {
        const boundingRect = this.getBoundingClientRect()
        const right = boundingRect.right - containerBoundingRect.left
        const visible = right <= containerBoundingRect.width
        $(this).toggleClass('hidden', !visible)
        if (!visible) {
          nav.attr('data-more', '1')
        }
      })
    } else if (layout === 'vertical') {
      this.blockContainer.on('click', '.myself-pageblocks-navigation-navlist-group', function () {
        $(this).next('ul').toggleClass('myself-pageblocks-navigation-navlist-show')
      })
      activeLinks.parents('.myself-pageblocks-navigation-navlist').addClass('myself-pageblocks-navigation-navlist-show')
    }
    activeLinks.parents('li').children().addClass('myself-pageblocks-navigation-active-link')
  }
}