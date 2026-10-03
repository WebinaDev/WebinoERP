export function BrandMark({ className = 'size-11' }: { className?: string }) {
  return (
    <svg
      className={className}
      viewBox="0 0 64 64"
      role="img"
      aria-hidden="true"
      xmlns="http://www.w3.org/2000/svg"
    >
      <rect width="64" height="64" rx="16" fill="#16130F" />
      <path
        d="M12 18 L22 46 L32 26 L42 46 L52 18"
        fill="none"
        stroke="#E4C27A"
        strokeWidth="6"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  )
}
