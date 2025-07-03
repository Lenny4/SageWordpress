import * as React from "react";

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
      id={`sage-tabpanel-${id}-${index}`}
      aria-labelledby={`sage-tab-${id}-${index}`}
      {...other}
    >
      {keepDom !== false ? children : <>{value === index && children}</>}
    </div>
  );
}
