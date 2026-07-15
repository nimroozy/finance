import { clsx, type ClassValue } from "@/lib/clsx";

export function cn(...inputs: ClassValue[]) {
  return clsx(inputs);
}

export type { ClassValue };
