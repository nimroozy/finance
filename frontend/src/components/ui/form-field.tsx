import { Label, FieldError } from "@/components/ui/form";
import { cn } from "@/lib/utils";

/** Label + control + inline error, consistently spaced. */
export function FormField({
  label,
  htmlFor,
  required,
  error,
  hint,
  children,
  className,
}: {
  label: string;
  htmlFor?: string;
  required?: boolean;
  error?: string;
  hint?: string;
  children: React.ReactNode;
  className?: string;
}) {
  return (
    <div className={cn("space-y-1.5", className)}>
      <Label htmlFor={htmlFor}>
        {label}
        {required ? (
          <span className="text-danger" aria-hidden>
            {" "}
            *
          </span>
        ) : null}
      </Label>
      {children}
      {hint && !error ? <p className="text-xs text-muted">{hint}</p> : null}
      <FieldError>{error}</FieldError>
    </div>
  );
}

/** Titled group of FormFields within a longer form. */
export function FormSection({
  title,
  description,
  children,
  className,
}: {
  title?: string;
  description?: string;
  children: React.ReactNode;
  className?: string;
}) {
  return (
    <div className={cn("space-y-4", className)}>
      {title ? (
        <div>
          <h3 className="text-sm font-semibold text-foreground">{title}</h3>
          {description ? <p className="mt-0.5 text-xs text-muted">{description}</p> : null}
        </div>
      ) : null}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">{children}</div>
    </div>
  );
}
