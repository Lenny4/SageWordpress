import * as React from "react";
import { FormContentInterface } from "../../../interface/InputInterface";
import { Grid } from "@mui/material";
import { FormTableComponent } from "./FormTableComponent";
import { TabsComponent } from "../tab/TabsComponent";
import { FormFieldComponent } from "./fields/FormFieldComponent";

const defaultContainer = Grid;
const defaultProps = {
  size: { xs: 12, md: 6 },
};

type State = {
  content: FormContentInterface;
  transPrefix: string;
};

export const FormContentComponent: React.FC<State> = ({
  content,
  transPrefix,
}) => {
  let { Container, props, Dom, fields, children, table, tabs } = content;
  Container = Container ?? defaultContainer;
  props = props ?? defaultProps;

  return (
    <>
      <Container {...props}>
        {Dom}
        {fields?.map((field, indexField) => (
          <FormFieldComponent
            key={indexField}
            field={field}
            transPrefix={transPrefix}
          />
        ))}
        {table && (
          <FormTableComponent table={table} transPrefix={transPrefix} />
        )}
        {children && children.length > 0 && (
          <>
            {children.map((child, indexChild) => (
              <FormContentComponent
                content={child}
                transPrefix={transPrefix}
                key={indexChild}
              />
            ))}
          </>
        )}
        {tabs && <TabsComponent tabs={tabs} />}
      </Container>
    </>
  );
};
