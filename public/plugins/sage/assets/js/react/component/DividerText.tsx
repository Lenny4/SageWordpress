import * as React from "react";
import { styled } from "@mui/material/styles";
import Divider from "@mui/material/Divider";

const Root = styled("div")(({ theme }) => ({
  width: "100%",
  ...theme.typography.body2,
  color: (theme.vars || theme).palette.text.secondary,
  "& > :not(style) ~ :not(style)": {
    marginTop: theme.spacing(2),
  },
}));

export type State = {
  text: React.ReactNode;
  textAlign?: "center" | "right" | "left";
};

export const DividerText: React.FC<State> = ({ text, textAlign }) => (
  <Root>
    <Divider textAlign={textAlign}>{text}</Divider>
  </Root>
);
