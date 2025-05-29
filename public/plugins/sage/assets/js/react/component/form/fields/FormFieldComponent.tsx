import * as React from "react";
import { IconButton, Tooltip } from "@mui/material";
import InfoIcon from "@mui/icons-material/Info";
import { getTranslations } from "../../../../functions/translations";
import { FieldInterface } from "../../../../interface/InputInterface";

let translations: any = getTranslations();

type State = {
  field: FieldInterface;
  transPrefix: string | undefined;
};

type State2 = {
  cannotBeChangeOnWebsite: boolean | undefined;
};

type State3 = {
  tooltip: string | undefined;
};

export const FieldTooltipComponent: React.FC<State3> = ({ tooltip }) => {
  return (
    <>
      {tooltip && (
        <div style={{ position: "relative", top: "-2px" }}>
          <Tooltip title={tooltip} arrow>
            <IconButton>
              <InfoIcon fontSize="small" />
            </IconButton>
          </Tooltip>
        </div>
      )}
    </>
  );
};

export const CannotBeChangeOnWebsiteComponent: React.FC<State2> = ({
  cannotBeChangeOnWebsite,
}) => {
  return (
    <>
      {cannotBeChangeOnWebsite && (
        <div style={{ position: "relative", top: "-2px" }}>
          <Tooltip title={translations.sentences.cannotBeChangeOnWebsite} arrow>
            <IconButton>
              <InfoIcon fontSize="small" />
            </IconButton>
          </Tooltip>
        </div>
      )}
    </>
  );
};

export const FormFieldComponent: React.FC<State> = ({ field, transPrefix }) => {
  let { label, name, DomField } = field;

  if (!label) {
    label = "";
    if (transPrefix && translations[transPrefix].hasOwnProperty(name)) {
      if (translations[transPrefix][name].hasOwnProperty("label")) {
        label = translations[transPrefix][name].label;
      } else {
        label = translations[transPrefix][name];
      }
    } else {
      label = translations.words[name] ?? name;
    }
  }

  return <DomField {...field} label={label} />;
};
