import TextField, { type TextFieldProps } from '@mui/material/TextField'
import {
  centsToEurosInput,
  eurosToCents,
  formatQuantityInput,
  sanitizeDecimalInput,
  sanitizeIntegerInput,
} from '../format'

export type NumberFieldMode = 'money' | 'quantity' | 'integer'

type NumberFieldProps = Omit<TextFieldProps, 'value' | 'onChange' | 'type'> & {
  mode: NumberFieldMode
  value: string
  onChange: (value: string) => void
}

export function keyboardSlot(inputMode: 'numeric' | 'tel') {
  return {
    htmlInput: {
      inputMode,
      lang: 'fr' as const,
      autoComplete: inputMode === 'tel' ? 'tel' : 'off',
      autoCorrect: 'off' as const,
      spellCheck: false,
    },
  }
}

export default function NumberField({
  mode,
  value,
  onChange,
  onBlur,
  slotProps,
  ...rest
}: NumberFieldProps) {
  const inputMode = mode === 'integer' ? 'numeric' : 'decimal'
  const extraHtmlInput =
    slotProps?.htmlInput && typeof slotProps.htmlInput === 'object' ? slotProps.htmlInput : {}

  return (
    <TextField
      {...rest}
      type="text"
      value={value}
      onChange={(e) => {
        const raw = e.target.value
        onChange(
          mode === 'integer' ? sanitizeIntegerInput(raw) : sanitizeDecimalInput(raw, mode === 'money' ? 2 : 4),
        )
      }}
      onBlur={(e) => {
        if (mode === 'money') onChange(centsToEurosInput(eurosToCents(value)))
        else if (mode === 'quantity') onChange(formatQuantityInput(value))
        onBlur?.(e)
      }}
      slotProps={{
        ...slotProps,
        htmlInput: {
          inputMode,
          lang: 'fr',
          autoComplete: 'off',
          autoCorrect: 'off',
          spellCheck: false,
          ...extraHtmlInput,
        },
      }}
    />
  )
}
