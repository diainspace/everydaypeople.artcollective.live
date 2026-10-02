import type { MockPost } from '../data/mockBlog'
import { ArtPanel } from './ArtPanel'

export function PostCard({ post, featured = false }: { post: MockPost; featured?: boolean }) {
  return (
    <article className={featured ? 'post-card post-card-featured' : 'post-card'}>
      <a className="post-card-art" href={`/stories/${post.slug}/`} aria-label={`Read ${post.title}`}>
        <ArtPanel className={post.artClass} alt={post.artAlt} label={post.number} />
        {!featured && <span className="story-image-overlay"><strong>{post.title}</strong></span>}
      </a>
      <div className="post-card-copy">
        <p className="meta"><span>{post.category}</span><time>{post.date}</time></p>
        <h2><a href={`/stories/${post.slug}/`}>{post.title}</a></h2>
        <p className="dek">{post.description}</p>
        <a className="text-link" href={`/stories/${post.slug}/`}>Read the story <span aria-hidden="true">↗</span></a>
      </div>
    </article>
  )
}
