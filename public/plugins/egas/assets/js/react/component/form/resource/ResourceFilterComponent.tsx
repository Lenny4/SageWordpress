// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import {createRoot} from "react-dom/client";
import React from "react";
import Box from "@mui/material/Box";

interface State {
  data: any;
  textInput: Element;
  checkboxInput: Element;
}

const ResourceFilterComponent: React.FC<State> = ({data, textInput, checkboxInput}) => {
  console.log(data, textInput, checkboxInput)
  return (
    <Box sx={{width: "100%"}}>
      okok
    </Box>
  );
};

const doms = document.querySelectorAll("[data-checkbox-resource]");

doms.forEach((dom) => {
  const root = createRoot(dom.querySelector("[data-react-resource]"));
  root.render(
    <ResourceFilterComponent
      data={JSON.parse($(dom).attr("data-checkbox-resource"))}
      textInput={dom.querySelector("input[type='text']")}
      checkboxInput={dom.querySelector("input[type='checkbox']")}
    />,
  );
});
