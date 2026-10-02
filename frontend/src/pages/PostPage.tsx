import { ArtPanel } from '../components/ArtPanel'
import type { MockPost } from '../data/mockBlog'

export function PostPage({ post }: { post: MockPost }) {
  return (
    <main className="article-page">
      <header className="article-header">
        <div>
          <p className="section-number">{post.number} / {post.category}</p>
          <h1>{post.title}</h1>
        </div>
        <div className="article-dek"><p>{post.description}</p><time>{post.date}</time></div>
      </header>
      <figure className="article-hero">
        <ArtPanel className={post.artClass} alt={post.artAlt} />
        <figcaption>Studio study / Everyday People Art Collective</figcaption>
      </figure>
      <article className="article-body">
        <p className="article-lead">{post.content[0]?.text}</p>
        {post.content.slice(1).map((block, index) => (
          <section key={index}>
            {block.heading && <h2>{block.heading}</h2>}
            <p>{block.text}</p>
          </section>
        ))}
      </article>
    </main>
  )
}
