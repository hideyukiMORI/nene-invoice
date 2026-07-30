import { useEffect, useRef, useState, type RefObject } from 'react'

export interface FileDropZone {
  /** Attach to the `<label>` that wraps the file input. */
  ref: RefObject<HTMLLabelElement | null>
  /** True while a drag hovers the zone — drive the visual state from this. */
  dragging: boolean
}

/**
 * Drag-and-drop file selection for a `<label>` that wraps a real
 * `<input type="file">`.
 *
 * The listeners are attached imperatively instead of as JSX props because
 * jsx-a11y strict rejects event handlers on non-interactive elements, and a
 * `<label>` is one. Dropping is a pointer-only enhancement — keyboard and
 * screen-reader users pick the same file through the wrapped input — so the
 * accessible behaviour is identical either way.
 *
 * `onFile` is read through a ref so the listeners are attached once and still
 * see the latest closure.
 */
export function useFileDropZone(onFile: (file: File) => void): FileDropZone {
  const ref = useRef<HTMLLabelElement>(null)
  const [dragging, setDragging] = useState(false)

  const onFileRef = useRef(onFile)
  useEffect(() => {
    onFileRef.current = onFile
  })

  useEffect(() => {
    const zone = ref.current
    if (zone === null) return

    const enter = (e: DragEvent): void => {
      e.preventDefault()
      setDragging(true)
    }
    // Without preventDefault on dragover the browser opens the file instead.
    const over = (e: DragEvent): void => {
      e.preventDefault()
    }
    const leave = (): void => {
      setDragging(false)
    }
    const drop = (e: DragEvent): void => {
      e.preventDefault()
      setDragging(false)
      const picked = e.dataTransfer?.files[0]
      if (picked !== undefined) onFileRef.current(picked)
    }

    zone.addEventListener('dragenter', enter)
    zone.addEventListener('dragover', over)
    zone.addEventListener('dragleave', leave)
    zone.addEventListener('drop', drop)
    return () => {
      zone.removeEventListener('dragenter', enter)
      zone.removeEventListener('dragover', over)
      zone.removeEventListener('dragleave', leave)
      zone.removeEventListener('drop', drop)
    }
  }, [])

  return { ref, dragging }
}
