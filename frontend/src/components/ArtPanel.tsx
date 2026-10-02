type ArtPanelProps = { className: string; alt: string; label?: string }

export function ArtPanel({ className, alt, label }: ArtPanelProps) {
  return (
    <div className={`art-panel ${className}`} role="img" aria-label={alt}>
      {label && <span>{label}</span>}
    </div>
  )
}
