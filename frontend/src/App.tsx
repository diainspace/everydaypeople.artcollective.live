import './App.css'
import { SiteFooter } from './components/SiteFooter'
import { SiteHeader } from './components/SiteHeader'
import { mockPosts } from './data/mockBlog'
import { ArchivePage } from './pages/ArchivePage'
import { AboutPage } from './pages/AboutPage'
import { HomePage } from './pages/HomePage'
import { PostPage } from './pages/PostPage'

function currentPage() {
  const path = window.location.pathname.replace(/\/+$/, '') || '/'
  if (path === '/about') return <AboutPage />
  if (path === '/stories') return <ArchivePage />
  if (path.startsWith('/stories/')) {
    const slug = path.slice('/stories/'.length)
    const post = mockPosts.find((candidate) => candidate.slug === slug) ?? mockPosts[0]
    return <PostPage post={post} />
  }
  return <HomePage />
}

function App() {
  return <><SiteHeader />{currentPage()}<SiteFooter /></>
}

export default App
