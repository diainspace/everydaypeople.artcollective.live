export function SiteHeader() {
  const currentPath = window.location.pathname
  return (
    <header className="site-header">
      <a className="wordmark" href="/" aria-label="Everyday People Art Collective home">
        <span>Everyday People</span><span>Art Collective</span>
      </a>
      <nav aria-label="Main navigation">
        <a href="/about/" aria-current={currentPath === '/about' || currentPath.startsWith('/about/') ? 'page' : undefined}>About</a>
      </nav>
    </header>
  )
}
