import * as React from "react";

interface TabPanelProps {
  children?: React.ReactNode;
  index: number;
  value: number;
  keepDom?: boolean;
}

export function CustomTabPanel(props: TabPanelProps) {
  const { children, value, index, keepDom, ...other } = props;

  return (
    <div
      role="tabpanel"
      hidden={value !== index}
      id={`simple-tabpanel-${index}`}
      aria-labelledby={`simple-tab-${index}`}
      {...other}
    >
      {keepDom !== false ? children : <>{value === index && children}</>}
    </div>
  );
}
