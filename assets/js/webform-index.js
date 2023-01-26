document.addEventListener('DOMContentLoaded', () => {
  const webforms = document.querySelectorAll('.os2forms-sync-webform-index .os2forms-sync-webform')
  const searchInput = document.querySelector('.os2forms-sync-webform-index [type="search"]')

  if (!(webforms && searchInput)) {
    return
  }

  /**
   * Combine values of all `data-indexed` attributes on descendants.
   */
  const index = (el) => {
    return [...el.querySelectorAll('[data-indexed]')]
      .map(e => e.dataset.indexed)
      .join(' ')
  }

  const liveSearch = () => {
    const query = searchInput.value.toLowerCase()
    webforms.forEach(webform => {
      if (!webform.indexed) {
        webform.indexed = index(webform).toLowerCase()
      }
      webform.hidden = !webform.indexed.includes(query)
    })
  }

  let typingTimer
  const typingDelay = 250

  searchInput.addEventListener('keyup', () => {
    clearTimeout(typingTimer)
    typingTimer = setTimeout(liveSearch, typingDelay)
  })
})
