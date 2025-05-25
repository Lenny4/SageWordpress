import React from "react";
import Box from "@mui/material/Box";
import { Tab, Tabs } from "@mui/material";
import { CustomTabPanel } from "./CustomTabPanel";
import { TabInterface } from "../../../interface/TabInterface";

type State = {
  tabs: TabInterface[];
  defaultActiveTab?: number;
};

export const TabsComponent = ({ tabs, defaultActiveTab }: State) => {
  const [tabValue, setValue] = React.useState(defaultActiveTab ?? 0);

  const handleChange = (event: React.SyntheticEvent, newValue: number) => {
    setValue(newValue);
  };

  return (
    <>
      <Box sx={{ borderBottom: 1, borderColor: "divider" }}>
        <Tabs
          value={tabValue}
          onChange={handleChange}
          aria-label="article tabs"
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
