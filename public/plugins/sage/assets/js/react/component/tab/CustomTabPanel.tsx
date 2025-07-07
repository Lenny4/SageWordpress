import * as React from "react";
import { TOKEN } from "../../../token";

interface TabPanelProps {
  children?: React.ReactNode;
  index: number;
  id: string;
  value: number;
  keepDom?: boolean;
}

export function CustomTabPanel(props: TabPanelProps) {
  const { children, value, index, keepDom, id, ...other } = props;

  return (
    <div
      role="tabpanel"
      hidden={value !== index}
      id={`${TOKEN}-tabpanel-${id}-${index}`}
      aria-labelledby={`${TOKEN}-tab-${id}-${index}`}
      {...other}
    >
      {keepDom !== false ? children : <>{value === index && children}</>}
    </div>
  );
}
