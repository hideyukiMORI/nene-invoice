import { Link, type LinkProps } from 'react-router-dom'
import { buttonClassNames, type ButtonSize, type ButtonVariant } from './button-styles'

export interface LinkButtonProps extends LinkProps {
  variant?: ButtonVariant
  size?: ButtonSize
}

/**
 * A react-router `Link` that looks like a {@link Button}. Use for navigating
 * page actions (e.g. 「新規作成」) that should read as buttons, not text links.
 */
export function LinkButton({
  variant = 'primary',
  size = 'md',
  className,
  children,
  ...rest
}: LinkButtonProps) {
  return (
    <Link className={buttonClassNames(variant, size, className)} {...rest}>
      {children}
    </Link>
  )
}
