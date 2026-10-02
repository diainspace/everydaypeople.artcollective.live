import { ArtPanel } from '../components/ArtPanel'
import { mockPosts } from '../data/mockBlog'

export function ArchivePage() {
  return (
    <main className="archive-page">
      <header className="page-intro">
        <p className="section-number">All entries / 2026</p>
        <h1>The<br />Archive</h1>
      </header>
      <ol className="archive-list">
        {mockPosts.map((post) => (
          <li key={post.id}>
            <a href={`/stories/${post.slug}/`}>
              <span className="archive-number">{post.number}</span>
              <span className="archive-art"><ArtPanel className={post.artClass} alt="" /></span>
              <span className="archive-copy"><strong>{post.title}</strong><small>{post.category} / {post.date}</small></span>
              <span className="archive-arrow" aria-hidden="true">↗</span>
            </a>
          </li>
        ))}
      </ol>
    </main>
  )
}
