import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { Button } from "@/components/Button";

describe("Button", () => {
  // Verifies the label is exposed as an accessible button (correct role + name),
  // which is what assistive tech and most queries rely on.
  it("renders its children as an accessible button", () => {
    render(<Button>Save</Button>);
    expect(screen.getByRole("button", { name: "Save" })).toBeInTheDocument();
  });

  // Verifies the safety default: type="button" so a Button placed inside a form
  // never triggers an accidental submit.
  it("defaults to type=button", () => {
    render(<Button>Go</Button>);
    expect(screen.getByRole("button")).toHaveAttribute("type", "button");
  });

  // Verifies the variant prop maps to the right token-backed classes, so the
  // three visual styles are actually distinct.
  it("applies variant-specific classes", () => {
    const { rerender } = render(<Button variant="primary">A</Button>);
    expect(screen.getByRole("button").className).toContain("bg-brand");

    rerender(<Button variant="secondary">A</Button>);
    expect(screen.getByRole("button").className).toContain("bg-surface");

    rerender(<Button variant="ghost">A</Button>);
    expect(screen.getByRole("button").className).toContain("bg-transparent");
  });

  // Verifies the click handler is forwarded and fires exactly once per click —
  // the core interaction contract the ArticleExplorer filter relies on.
  it("calls onClick when activated", async () => {
    const onClick = jest.fn();
    render(<Button onClick={onClick}>Click</Button>);

    await userEvent.click(screen.getByRole("button", { name: "Click" }));

    expect(onClick).toHaveBeenCalledTimes(1);
  });

  // Verifies a disabled button is both marked disabled and does not fire onClick.
  it("does not fire onClick when disabled", async () => {
    const onClick = jest.fn();
    render(
      <Button disabled onClick={onClick}>
        Nope
      </Button>,
    );

    const button = screen.getByRole("button", { name: "Nope" });
    expect(button).toBeDisabled();
    await userEvent.click(button);
    expect(onClick).not.toHaveBeenCalled();
  });
});
