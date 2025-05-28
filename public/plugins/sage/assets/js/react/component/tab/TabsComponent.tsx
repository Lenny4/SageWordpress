import React from "react";
import Box from "@mui/material/Box";
import { Tab, Tabs } from "@mui/material";
import { CustomTabPanel } from "./CustomTabPanel";
import { TabInterface } from "../../../interface/TabInterface";
import { TabsProps } from "@mui/material/Tabs/Tabs";

type State = {
  tabs: TabInterface[];
  tabProps?: TabsProps;
};

export const TabsComponent = ({ tabs, tabProps }: State) => {
  const [tabValue, setValue] = React.useState(
    Number(tabProps?.defaultValue ?? 0),
  );

  const handleChange = (event: React.SyntheticEvent, newValue: number) => {
    setValue(newValue);
  };

  return (
    <>
      <Box sx={{ borderBottom: 1, borderColor: "divider" }}>
        <Tabs
          {...tabProps}
          value={tabValue}
          onChange={handleChange}
          variant="scrollable"
          scrollButtons="auto"
          aria-label="scrollable auto tabs"
        >
          {tabs.map((tab, index) => (
            <Tab label={tab.label} key={index} />
          ))}
        </Tabs>
      </Box>
      {tabs.map((tab, index) => (
        <CustomTabPanel value={tabValue} index={index} key={index}>
          {tab.dom}
        </CustomTabPanel>
      ))}
    </>
  );
};
