import React from "react";
import Box from "@mui/material/Box";
import { Tab, Tabs } from "@mui/material";
import { CustomTabPanel } from "./CustomTabPanel";
import { FormTabInterface } from "../../../interface/InputInterface";

type State = {
  tabs: FormTabInterface;
};

export const TabsComponent = ({ tabs }: State) => {
  const [tabValue, setValue] = React.useState(
    Number(tabs.tabProps?.defaultValue ?? 0),
  );

  const handleChange = (event: React.SyntheticEvent, newValue: number) => {
    setValue(newValue);
  };

  React.useEffect(() => {
    window.addEventListener(`sage-tabpanel-${tabs.id}`, (e) => {
      // @ts-ignore
      setValue(Number(e.detail));
    });
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  return (
    <>
      <Box sx={{ borderBottom: 1, borderColor: "divider" }}>
        <Tabs
          {...tabs.tabProps}
          value={tabValue}
          onChange={handleChange}
          variant="scrollable"
          scrollButtons="auto"
          aria-label="scrollable auto tabs"
        >
          {tabs.tabs.map((tab, index) => (
            <Tab label={tab.label} key={index} />
          ))}
        </Tabs>
      </Box>
      {tabs.tabs.map((tab, index) => (
        <CustomTabPanel value={tabValue} index={index} key={index} id={tabs.id}>
          {tab.dom}
        </CustomTabPanel>
      ))}
    </>
  );
};
