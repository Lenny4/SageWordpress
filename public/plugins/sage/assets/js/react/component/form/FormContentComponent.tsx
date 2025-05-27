import * as React from "react";
import { FormContentInterface } from "../../../interface/InputInterface";
import { Grid } from "@mui/material";
import { FormTableComponent } from "./FormTableComponent";
import { FormFieldComponent } from "./FormFieldComponent";
import { TabsComponent } from "../tab/TabsComponent";

const defaultContainer = Grid;
const defaultProps = {
  size: { xs: 12, md: 6 },
};

type State = {
  content: FormContentInterface[];
  transPrefix: string;
};

export const FormContentComponent: React.FC<State> = ({
  content,
  transPrefix,
}) => {
  return (
    <>
      {content.map(
        (
          { Container, props, Dom, fields, children, table, tabs },
          indexContainer,
        ) => {
          Container = Container ?? defaultContainer;
          props = props ?? defaultProps;

          return (
            <Container {...props} key={indexContainer}>
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
                <FormContentComponent
                  content={children}
                  transPrefix={transPrefix}
                />
              )}
              {tabs && <TabsComponent tabs={tabs} />}
            </Container>
          );
        },
      )}
    </>
  );
};
