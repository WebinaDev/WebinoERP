import { fireEvent, render, screen } from "@testing-library/react"
import { useState } from "react"
import { expect, test, vi } from "vitest"
import { RepeaterInput } from "./fields"

vi.mock("next-intl", () => ({ useLocale: () => "en", useTranslations: () => (key: string) => key }))
vi.mock("./NavMenuField", () => ({ NavMenuField: () => null }))
vi.mock("@/features/ai/content-assist", () => ({ AiAssist: () => null }))
vi.mock("@/components/LocaleDatePicker", () => ({ LocaleDatePicker: () => null }))
vi.mock("@/components/content/MediaPickerDialog", () => ({ MediaPickerDialog: () => null }))
vi.mock("../render/extra-widgets", () => ({ WIcon: () => null }))

test("a single-field legacy repeater can add a row", () => {
  function Editor() {
    const [value, setValue] = useState<unknown>("")
    return <><RepeaterInput value={value} fields={[{ key: "text", label: "Text", kind: "text" }]} onChange={setValue} /><output data-testid="value">{JSON.stringify(value)}</output></>
  }
  render(<Editor />)
  fireEvent.click(screen.getByText("repeater_add"))
  const input = screen.getByRole("textbox") as HTMLInputElement
  fireEvent.change(input, { target: { value: " " } })
  expect(input.value).toBe(" ")
  fireEvent.change(input, { target: { value: " example" } })
  expect(screen.getByTestId("value").textContent).toBe(JSON.stringify(" example"))
  expect((screen.getByRole("textbox") as HTMLInputElement).value).toBe(" example")
  fireEvent.change(screen.getByRole("textbox"), { target: { value: " example " } })
  expect((screen.getByRole("textbox") as HTMLInputElement).value).toBe(" example ")
})

test("editing a structured repeater retains unexposed properties", () => {
  function Editor() {
    const [value, setValue] = useState<unknown>([{ text: "before", extra: { enabled: true } }])
    return <><RepeaterInput value={value} fields={[{ key: "text", label: "Text", kind: "text" }]} onChange={setValue} /><output data-testid="value">{JSON.stringify(value)}</output></>
  }
  render(<Editor />)
  fireEvent.change(screen.getByRole("textbox"), { target: { value: "after" } })
  expect(JSON.parse(screen.getByTestId("value").textContent ?? "")).toEqual([{ text: "after", extra: { enabled: true } }])
})
