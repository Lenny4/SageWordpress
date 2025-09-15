import React from "react";

export interface TabInterface {
  label: string;
  dom: React.ReactNode;
  ref: React.RefObject<any>;
}
