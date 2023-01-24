document.addEventListener('DOMContentLoaded', () => {
  const webforms = document.querySelectorAll('.os2forms-sync-webform-index .os2forms-sync-webform')
  const searchInput = document.querySelector('.os2forms-sync-webform-index [type="search"]')

  const liveSearch = () => {
    console.log('liveSearch', searchInput.value)
    const query = searchInput.value
    webforms.forEach(webform => {
      webform.hidden = !webform.innerText.toLowerCase().includes(query)
    })
  }

  let typingTimer
  const typingDelay = 250

  searchInput.addEventListener('keyup', () => {
    clearTimeout(typingTimer)
    typingTimer = setTimeout(liveSearch, typingDelay)
  })
})
